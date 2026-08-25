<?php

/*
 * Tzeentch - A load-balancing redirector for privacy-oriented alternative frontends. 
 * 
 * https://github.com/thefranke/tzeentch
 *
 */

$params = "";
$last_updated = "";
$config = "";

function fetch_param_str()
{
    $params = $_GET;
    $pstr = "";
    foreach($params as $k => $v)
    {
        $pstr .= $k;
        
        if ($v) 
            $pstr .= "=" . $v;
    }
    return $pstr;
}

function fetch_json($path)
{
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP',
            ]
        ]
    ];

    $context = stream_context_create($opts);
    $json_raw = @file_get_contents($path, false, $context);
    return json_decode($json_raw, true);
}

function fetch_gh_timestamp($repo, $file)
{
    $url = "https://api.github.com/repos/$repo/commits?path=$file";
    $json = fetch_json($url);
    return $json[0]["commit"]["author"]["date"];
}

function fetch_gh_content($repo, $file)
{
    $url = "https://raw.github.com/$repo/main/$file";
    $json = fetch_json($url);
    return $json;
}

function load_config()
{
    global $config;
    global $last_updated;

    // check for local configuration, early out
    $local_config = "data.json";
    if (file_exists($local_config))
    {
        $config = fetch_json($local_config);
        $last_updated = "Using local synced " . date ("Y-m-d H:i:s T", filemtime($local_config));
        return;
    }

    $repos = [
        ["thefranke/tzeentch", "data.json"],
        ["libredirect/instances", "data.json"],
        ["benbusby/farside", "services-full.json"]
    ];

    foreach($repos as $repo)
    {
        if ($config)
            break;

        $config = fetch_gh_content($repo[0], $repo[1]);
        
        if ($config) 
        {
            $last_updated = "Using " . $repo[0] . " synced " . fetch_gh_timestamp($repo[0], $repo[1]);
        
            // convert to libredirect format
            if ($repo[0] == "benbusby/farside")
            {
                $new_config = array();
                foreach ($config as $service)
                {
                    $type = $service["type"];
                    $instances = $service["instances"];
                    $new_config[$type] = array();
                    $new_config[$type]["clearnet"] = $instances;
                }
                $config = $new_config;
            }
        }
    }
}

function show_frontpage($config)
{
    if (!$config)
    {
        print("Error: Services configuration could not be loaded");
        return;
    }
    print('
        <h2>Frontends</h2>
        <ul>');

    foreach($config as $service => $instances)
    {
        $service = strtolower($service);

        print('
            <li><a id="' . $service . '" href="?' . $service . '">' . $service . '</a>');
        print('
            <ul>');
        
        if (array_key_exists("clearnet", $instances))
            foreach ($instances["clearnet"] as $instance)
            {
                $instance = explode("|", $instance);
                print('
                <li><a href="' . $instance[0] . '">' . $instance[0] . '</a></li>');
            }

        print('
            </ul>');
    }

    print('
        </ul>');
}

function show_redirector_config($config)
{
    if (!$config)
    {
        print("Error: Services configuration could not be loaded");
        return;
    }

    $tzeentch_instance = "$_SERVER[REQUEST_SCHEME]://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]";

    foreach($config as $service => $instances)
    {
        if (!array_key_exists($service, $_GET))
            continue;

        if (!array_key_exists("pattern", $config[$service]))
            continue;

        $pattern = $config[$service]["pattern"];
        
        $rdrto = "$2";
        if (array_key_exists("rdrto", $config[$service]))
            $rdrto = $config[$service]["rdrto"];

        $example = $pattern;
        if (array_key_exists("example", $config[$service]))
            $example = $config[$service]["example"];

        $rdrUrl = $tzeentch_instance . "?" . $service;
        if ($rdrto != "")
            $rdrUrl = $rdrUrl . "/" . "$rdrto";
        else
            $rdrUrl = $rdrUrl . "/";

        $redirects[] = array(
            "description"    => $service,
            "exampleUrl"     => $example,
            "exampleResult"  => $rdrUrl,
            "error"          => null,
            "includePattern" => $pattern,
            "excludePattern" => "",
            "patternDesc"    => "",
            "redirectUrl"    => $rdrUrl,
            "patternType"    => "W",
            "processMatches" => "noProcessing",
            "disabled"       => false,
            "grouped"        => false,
            "appliesTo"      => array("main_frame")
        );
    }

    $c = array(
        "createdBy" => "Tzeentch - Changer of Ways, Great Mutator, Lord of Entropy",
        "createdAt" => date("Y-m-d")."T".date("H:i:s")."Z",
        "redirects" => $redirects
    );

    header("Content-type: application/json; charset=utf-8");
    die(json_encode($c));
}

function show_redirector_config_selection($config)
{
    if (!$config)
    {
        print('Error: Services configuration could not be loaded');
        return;
    }

    print('
        <h3>Select frontends to create configuration for</h3>');

    print('
        <form action="?">
            <input type="hidden" name="_create_config" value="True">');

    foreach($config as $service => $instances)
    {
        if (!array_key_exists("pattern", $config[$service]))
            continue;

        print('<span><input type="checkbox" name="' . $service . '" value="True"> <a href="?' . $service . '">' . $service . '</a></span>');
    }

    print('
        <input type="submit" value="Create configuration">
        </form>');
}

function forward_to_random_instance($config, $param)
{
    $param = fetch_param_str();
    $params = explode("/", $param, 2);
    $frontend = $params[0];
    $frontend_param = implode(array_slice($params, 1));

    foreach($config as $service => $instances)
    {
        $service = strtolower($service);
        if($frontend != $service)
            continue;

        $k = array_rand($instances["clearnet"], 1);
        $random_instance = $instances["clearnet"][$k];
        die(header('Location: '.$random_instance . "/" . $frontend_param));
    }
}

load_config();

$params = $_GET;
if($params)
{
    if (array_key_exists("_create_config", $params))
        show_redirector_config($config);

    else if (!array_key_exists("_redirector_config", $params))
        forward_to_random_instance($config, array_key_first($params));
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Tzeentch</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="shortcut icon" href="https://raw.githubusercontent.com/Warhammer40kGroup/wh40k-icon/refs/heads/master/src/svgs/tzeentch-icon-01.svg">
        <style>
            html {
                font-family: monospace;
                font-size: 14pt;
                color: #111;
                background-color: #eee;
                text-align: center;
            }
            form {
                width: max-content;
                margin: auto;
                margin-top: 1em;
            }
            form span {
                display: block;
                text-align: left;
                margin-bottom: 0.2em;
            }
            input {
                margin: auto;
                font-size: 1.6em;
            }
            input:last-child {
                margin-top: 1em;
                margin-bottom: 1em;
            }
            h1, h2, a {
                color: #17c;
            }
            h1 {
                font-size: 2em;
            }
            h2 {
                font-size: 1.6em;
            }
            h3 {
                font-size: 1.2em;
            }
            section, header, footer {
                margin: auto;
                width: 50%;
                border-top: dashed 1px gray;
                padding-top: 1em;
                padding-bottom: 1em;
                text-align: center;
                overflow: auto;
            }
            header {
                text-align: center;
                border: 0px;
                padding-top: 50pt;
            }
            header img {
                border-radius: 10%;
                width: 10em;
            }
            h1, h2, h3, h4 {
                margin-top: 5pt;
                margin-bottom: 5pt;
            }
            ol, ul {
                text-align: left;
                list-style-type: disc;
                list-style-position: inside;
            }
            li {
                width: 100%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            img:not([src$='.svg']) {              
                border-radius: 10%;
            }
            @media (prefers-color-scheme: dark) {
                html {
                    background: #111;
                    color: #eee;
                }
                h1, h2, a {
                    color: #f93;
                }
                form, 
                img[src$='.svg'] {
                    filter: invert(1) !important;
                }
                form a {
                    color: #17c;
                }
            }
            @media only screen and (min-resolution: 3dppx) {
                html {
                    font-size: 36pt;
                }
                li {
                    margin-bottom: 5pt;
                }
            }
            @media only screen and (orientation: portrait) { 
                section, header, footer {
                    width: 95%;
                }
            }
        </style>
    </head>
    <body>
        <header>
            <img alt="" src="https://raw.githubusercontent.com/Warhammer40kGroup/wh40k-icon/refs/heads/master/src/svgs/tzeentch-icon-01.svg">
            <h1>Tzeentch</h1>
            <h3>"Changer of Ways, Great Mutator, Lord of Entropy"</h3>
            <h3>[<a href="https://github.com/thefranke/tzeentch">Github</a>] [<a href="?_redirector_config">Create Redirector config</a>]</h3>
        </header>
        <section>
            <?php
                if (array_key_exists("_redirector_config", $params))
                    show_redirector_config_selection($config);
                else    
                    show_frontpage($config); 
            ?>
        </section>
        <footer>
            <h2>Instance info</h2>
            <p><?php print($last_updated); ?></p>
        </footer>
    </body>
</html>