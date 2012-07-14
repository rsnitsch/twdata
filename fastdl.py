#!/usr/bin/python

"""
Downloads up to 10 files simultaneously (and thus making use of very fast
internet connections.) Especially useful when downloading from many different
servers that have bandwidth limitations.

@author: Robert Nitsch
"""

import sys,os,urllib,time
import multiprocessing
from multiprocessing import Pool

def usage():
    print "%s <destination-directory> <url1>[|destfile1] [<url2>[|destfile2]] [<url3>[|destfile3]] ...\n" % sys.argv[0]
    
def main():
    if len(sys.argv) < 3:
        usage()
        sys.exit(1)
        
    # get arguments
    destination_directory = sys.argv[1]
    urls = sys.argv[2:]
    
    # validate destination directory
    if not os.path.isdir(destination_directory):
        print "Error: invalid destination directory\n"
        sys.exit(2)
        
    # make urls distinct, so there is no URL that is downloaded twice.
    # not only because it would be a waste of bandwidth, it could also
    # cause collisions between the workers.
    urls = list(set(urls))
    
    pool = Pool(10)
    
    try:
        downloaded = pool.map(worker,
                              zip(urls,
                                  [destination_directory for url in urls]))
        #while True:
        #    try:
        #         = resultobj.get(1)
        #    except multiprocessing.TimeoutError, exc:
        #        pass
    except KeyboardInterrupt, exc:
        # @todo: THIS DOES ABSOLUTELY NOT WORK! FOR GODS FUCKING FUCK SAKE...
        # multiprocessing + keyboardinterrupt = FUCKING FUCKING FUCKER-EVIL!
        print "Waiting for workers to finish... hit CTRL-C again to force exit (no cleanup!)"
        pool.close()
        pool.terminate()
        pool.join()
        raise
    
    failed_urls = []
    for result in zip(urls, downloaded):
        url, download_okay = result
        
        if not download_okay:
            failed_urls.append(url)
    
    if len(failed_urls) > 0:
        print "\nFailed URLs:"
        for url in failed_urls:
            print urls
        print
        
    pool.close()
    pool.join()

    
def worker(args):
    try:
        url, destdir = args
        
        if "|" in url:
            _url, destfile = url.split("|")
        else:
            _url = url
            destfile = os.path.join(destdir, os.path.basename(url))
            
        print "Worker %d: Downloading '%s' ..." % (os.getpid(), url)
        
        urllib.urlretrieve(_url, destfile)
        
        return True
    except urllib.ContentTooShortError, exc:
        print "ERROR: ContentTooShort. URL: '%s'" % url
        
        # ensure there is never an incomplete file left
        if os.path.exists(destfile):
            os.remove(destfile)
    except IOError, exc:
        print "ERROR: %s. URL: '%s'" % (exc, url)
        
        # ensure there is never an incomplete file left
        if os.path.exists(destfile):
            os.remove(destfile)
    except KeyboardInterrupt, exc:
        print "Worker %d aborted." % os.getpid()
        
        # ensure there is never an incomplete file left
        if os.path.exists(destfile):
            os.remove(destfile)
            
    return False

if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt, exc:
        print "fastdl aborted."