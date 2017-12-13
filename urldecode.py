#!/usr/bin/python
import sys, urllib, time, os
from subprocess import call, Popen

"""
Applies urllib.unquote_plus to whole files.

@author: Robert Nitsch
"""

def decode(filename):
    call(["./twdecoder", filename, filename + ".decoded"], shell=False)
    
    os.remove(filename)
    os.rename(filename + ".decoded", filename)
    
def _decode(sfilename):
    t_start = time.time()

    dfilename = sfilename+".decoded"

    sfile = open(sfilename, 'r')
    dfile = open(dfilename, 'w')

    uqp = urllib.unquote_plus
    for line in sfile:
        dfile.write(uqp(line))

    sfile.close()
    dfile.close()

    t_end = time.time()

    t_duration = t_end - t_start
    
    os.remove(sfilename)
    os.rename(dfilename, sfilename)

def main():
    if(len(sys.argv) < 2):
        print "Usage: %s <file1> [<file2> ...]" % sys.argv[0]
        print "In the given files, replaces %xx escapes by their single-character equivalent \
and plus signs by spaces."
        sys.exit(1)
        
    if not os.path.isfile("twdecoder"):
        print "Error: twdecoder executable does not exist. You need to compile twdecoder.cpp first."
        print "Hint: To compile, execute the following command: g++ twdecoder.cpp -o twdecoder -O3 -Wall"
        sys.exit(1)
    
    files = sys.argv[1:]
    
    for file in files:
        if(not os.path.exists(file)):
            print "Error: file '%s' doesnt exist." % file
            continue
        
        decode(file)

if __name__=='__main__':
    main()
