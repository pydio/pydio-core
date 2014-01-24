__author__ = 'charles'
import logging
import argparse
import os

if __name__ == "__main__":

    logging.basicConfig(filename= os.path.dirname(os.path.realpath(__file__)) + '/framework.log',
                        level=logging.DEBUG,
                        format='%(asctime)s - %(message)s',
                        datefmt='%Y-%m-%d %H:%M:%S')

    parser = argparse.ArgumentParser('Pydio Framework watcher')
    parser.add_argument('-a', '--action', help='Event type', type=unicode, default=None)
    parser.add_argument('-p', '--path', help='Node path', default=None)
    args, _ = parser.parse_known_args()

    path = args.path
    logging.info("Event " + args.action +  " on " + args.path)