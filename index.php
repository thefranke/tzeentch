<!DOCTYPE html>
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

function fetch_param()
{
    $params = $_GET;
    $pstr = "";
    foreach($params as $k => $v)
    {
        $pstr .= $k . "=" . $v;
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

function print_frontpage($config)
{
    if (!$config)
    {
        echo "Error: Services configuration could not be loaded";
        return;
    }

    foreach($config as $service => $instances)
    {
        $service = strtolower($service);

        echo "<li><a href=\"?" . $service . "\">" . $service . "</a></li>\n";
        echo "<ul>\n";
        
        foreach ($instances["clearnet"] as $instance)
        {
            $instance = explode("|", $instance);
            echo "<li><a href=\"" . $instance[0] . "\">" . $instance[0] . "</a></li>\n";
        }

        echo "</ul>\n";
    }
}

function forward_to_random_instance($config, $param)
{
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

$param = fetch_param();
load_config();

if($param)
    forward_to_random_instance($config, $param);

?>
<html lang="en">
<head>
  <title>Tzeentch</title>
  <style>
    html {
      font-family: monospace;
      font-size: 16px;
      color: #66397C;
      text-align: center;
    }
    #main {
      text-align: left;
      margin: auto;
      min-width: 500px;
      display: inline-block;
    }
    hr {
      border: 1px dashed;
    }
    a:link, a:visited {
      color: #66397C;
    }
    ul {
      margin: 10px;
    }
    #header {
        text-align: center;
        margin: 40pt;
    }
    h1, h2, h3, h4 {
        margin: 5pt;
    }
    img {
        width: 120pt;
    }
  </style>
</head>
<body>
    <div id="main">
        <div id="header">
            <img src="https://bakadesign.dk/backoffice/wp-content/uploads/2019/10/Star-of-Chaos-02.svg">

            <h1>Tzeentch</h1>
            <h3>"Changer of Ways, Great Mutator, Lord of Entropy"</h3>
            <h4>[<a href="https://github.com/thefranke/tzeentch">Github</a>]</h4>
            <hr>
            <h4><?php echo $last_updated; ?></h3>
        </div>
        <ul>
            <?php print_frontpage($config); ?>
        </ul>
    </div>
</body>

</html>