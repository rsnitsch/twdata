#!/usr/bin/env python
import sys, os, time, urllib, subprocess
from xml.dom.minidom import parse

URLS = {}

def main():
    URLS = get_urls()
    
    # examine which worlds need to be updated
    worlds_to_update = os.listdir("/home/nopaste/production/data/server/de")
    before = set(worlds_to_update)
    
    worlds_to_update = filter(lambda x: x in URLS.keys(), worlds_to_update)
    after = set(worlds_to_update)
    
    worlds_no_more = before.difference(after)
    for world in worlds_no_more:
        print "Warning: world '%s' does no more exist!" % world
    
    # try to unzip old files
    # todo
    
    # examine which files need to be downloaded.
    # each tuple consists of (url, destination_file)
    files_to_download = [Download(URLS[id]+"/map/village.txt.gz",
                                  "data/%s_village.txt.gz" % id) \
                         for id in worlds_to_update]
    
    Timer.start()
    
    # 1. download
    
    # preserve files that have been already been imported in the last hour
    really_needed = []
    for file in files_to_download:
        # *.txt (the decoded variant)
        candidate = file.destination_file[:-3]
        
        if os.path.isfile(candidate) and (time.time() - os.stat(candidate).st_mtime) < 3600:
            continue # we dont need to download this file yet again
        else:
            really_needed.append(file)
    
    #print "\n".join([str(i) for i in really_needed])
    
    if len(really_needed) > 0:
        # create urlstring
        # e.g.: http://de30.ds.de/village.txt.gz|data/de30_village.txt.gz ...
        urlstring = " ".join(["%s\|%s" % (item.remote_url, item.destination_file) \
                              for item in really_needed])
        subprocess.call("python2.6 fastdl.py %s %s" % ("data", urlstring),
                        shell=True)
    else:
        print "No files to download. Each file has already been downloaded in the last 3600 seconds."
        print "Nothing to be done."
        sys.exit(0)
        
    Timer.interval_msg("Downloading took %f seconds.", "downloading")
    
    # 2. unzip
    files_to_unzip = [file.destination_file for file in really_needed]
    if len(files_to_unzip) > 0:
        for file in files_to_unzip:
            subprocess.call("gunzip -f %s" % file,
                            shell=True)
    files_unzipped = filter(lambda x: os.path.isfile(x), [file[:-3] for file in files_to_unzip])
    files_not_unzipped = set(files_to_unzip).difference(set(files_unzipped))
    
    for file in files_not_unzipped:
        "File %s could not be unzipped."
    
    Timer.interval_msg("Unzipping took %f seconds.", "unzipping")
    
    # 3. decode
    files_to_decode = [file[:-3] for file in files_to_unzip]
    if len(files_to_decode) > 0:
        subprocess.call("python urldecode.py %s" % " ".join(files_to_decode),
                        shell=True)
    
    Timer.interval_msg("Decoding took %f seconds.", "decoding")
    
    # 4. import
    files_to_import = list(files_to_decode)
    if len(files_to_import) > 0:
        if os.path.isfile("import.php.lock"):
            print "ERROR: IMPORT SCRIPT LOCKED!"
        else:
            subprocess.call("php import.php %s" % " ".join(files_to_import),
                            shell=True)
    
    Timer.interval_msg("Importing took %f seconds.", "importing")
    
    print
    print "Summary:"
    print " Downloading: %ss" % Timer.get("downloading", 3)
    print " Unzipping:   %ss" % Timer.get("unzipping", 3)
    print " Decoding:    %ss" % Timer.get("decoding", 3)
    print " Importing:   %ss" % Timer.get("importing", 3)
    
def get_urls():
    if not os.path.exists("cache/urls_xml.cache") or \
        (time.time() - os.stat("cache/urls_xml.cache").st_mtime) > 3600:
        urllib.urlretrieve("http://www.die-staemme.de/backend/get_servers_xml.php",
                           "cache/urls_xml.cache")
    
    xml = parse("cache/urls_xml.cache")
    
    urls = {}
    
    xml_urls = xml.getElementsByTagName('url')
    for xml_url in xml_urls:
        urls[xml_url.getAttribute('id')] = xml_url.firstChild.nodeValue
        
    return urls
    
class Download(object):
    def __init__(self, remote_url, destination_file):
        self.remote_url         = remote_url
        self.destination_file   = destination_file

    def __str__(self):
        print "(%s, %s)" % (self.remote_url, self.destination_file)
        
class World(object):
    def __init__(self, id):
        self.url = URLS[id]
        self.id = id
        self.rvillage = self.url + "/map/village.txt.gz"
        self.rally    = self.url + "/map/ally.txt.gz"
        self.rplayer  = self.url + "/map/player.txt.gz"
        self.rconquer = self.url + "/map/conquer.txt.gz"
        self.lvillage = os.path.join("data/%s_village.txt.gz")
        self.lally    = os.path.join("data/%s_ally.txt.gz")
        self.lplayer  = os.path.join("data/%s_player.txt.gz")
        self.lconquer = os.path.join("data/%s_conquer.txt.gz")
    
class Timer(object):
    starttime = 0
    times = {}
    
    @staticmethod
    def start():
        Timer.starttime = time.time()
    
    @staticmethod
    def save(id):
        Timer.times[id] = (time.time() - Timer.starttime)
        
    @staticmethod
    def get(id, digits=None):
        if digits is not None:
            return round(Timer.times[id], digits)
        return Timer.times[id]
        
    @staticmethod
    def interval_msg(msg, save_id=None):
        if save_id is not None:
            Timer.save(save_id)
        
        print msg % round(time.time() - Timer.starttime, 3)
        Timer.start()

if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt, exc:
        print "myupdater aborted."
    