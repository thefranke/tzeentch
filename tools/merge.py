#!/usr/bin/env python3
#
# This tool merges lists of instances from other projects.
# If you like to add your own, create own.json and use the libredirect format.
#

import requests
import json
import hashlib

from boltons.setutils import IndexedSet

data = {}

def write_json(filename, data):
    with open(filename, 'w') as outfile:
        json_string = json.dumps(data)
        json.dump(data, outfile, indent=2)

def load_json(filename):
    try:
        with open(filename) as f:
            data = json.load(f)
            return data
    except:
        pass

    return {}

def get_json(url):
    try:
        req = requests.get(url)
        req.raise_for_status()
        return req.json()
    except:
        pass

    return {}

def merge_instances(frontend, new_instances):
    global data

    frontend = frontend.lower()
    current_instances = data.get(frontend, {}).get("clearnet", [])
    current_instances = list(IndexedSet(new_instances).union(IndexedSet(current_instances)))

    clean_instances = []
    for c in current_instances:
        if ".onion" in c: continue
        clean_instances.append(c)

    if not frontend in data: data[frontend] = {}
    data[frontend]["clearnet"] = clean_instances

# merge libredirect
url = "https://raw.githubusercontent.com/libredirect/instances/main/data.json"
get_json(url)
data_json = get_json(url)
for frontend in data_json:
   new_instances = data_json[frontend]["clearnet"]
   merge_instances(frontend, new_instances)

# merge farside
url = "https://raw.githubusercontent.com/benbusby/farside/main/services-full.json"
data_json = get_json(url)
for frontend_data in data_json:
   frontend = frontend_data["type"]
   new_instances = frontend_data["instances"]
   merge_instances(frontend, new_instances)

# merge noplagarism frontend-instances
url = "https://raw.githubusercontent.com/NoPlagiarism/frontend-instances-list/master/instances/all.json"
data_json = get_json(url)
for frontend in data_json:
   new_instances_prot = data_json[frontend]["instances"]
   new_instances = []
   for n in new_instances_prot: 
    new_instances.append("https://%s" % (n))
   if " (discontinued)" in frontend: frontend = frontend.split(" ")[0]
   if frontend == "lingvatranslate": frontend = "lingva"
   merge_instances(frontend, new_instances)

# merge own (libredirect format)
url = "own.json"
data_json = load_json(url)
for frontend in data_json:
   new_instances = data_json[frontend]["clearnet"]
   merge_instances(frontend, new_instances)

# settings
url = "settings.json"
data_json = load_json(url)
for frontend in data_json:
    if frontend in data:
        data[frontend] = {**data[frontend], **data_json[frontend]}

# write result
write_json("../data.json", data)

# warn if settings are missing
for frontend in data:
    if frontend not in data_json:
        print("Missing settings for %s" % (frontend))
