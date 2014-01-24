__author__ = 'charles'
import time
import os
import sys
import argparse
import threading
from watchdog.observers import Observer
import logging
import fnmatch

from watchdog.events import LoggingEventHandler, FileSystemEventHandler


class PydioEventHandler(FileSystemEventHandler):

    def __init__(self, basepath, includes, excludes):
        super(PydioEventHandler, self).__init__()
        self.base = basepath
        self.includes = includes
        self.excludes = excludes

    def remove_prefix(self, text):
        return text[len(self.base):] if text.startswith(self.base) else text

    def included(self, event, base=None):
        if not base:
            base = os.path.basename(event.src_path)
        for i in self.includes:
            if not fnmatch.fnmatch(base, i):
                return False
        for e in self.excludes:
            if fnmatch.fnmatch(base, e):
                return False
        return True

    def on_moved(self, event):
        if not self.included(event):
            return
        logging.debug("Event: move noticed: " + event.event_type + " on file " + self.remove_prefix(event.src_path)
                      + " at " + time.asctime())
        t = (
            self.remove_prefix(event.dest_path.decode('utf-8')),
            self.remove_prefix(event.src_path.decode('utf-8')),
        )

    def on_created(self, event):
        if not self.included(event):
            return
        logging.debug("Event: creation noticed: " + event.event_type +
                         " on file " + self.remove_prefix(event.src_path) + " at " + time.asctime())

        search_key = self.remove_prefix(event.src_path)
        if event.is_directory:
            hash_key = 'directory'
        else:
            #hash_key = hashfile(open(event.src_path, 'rb'), hashlib.md5())
            pass

    def on_deleted(self, event):
        if not self.included(event):
            return
        logging.debug("Event: deletion noticed: " + event.event_type +
                         " on file " + self.remove_prefix(event.src_path) + " at " + time.asctime())

    def on_modified(self, event):
        super(PydioEventHandler, self).on_modified(event)
        if not self.included(event):
            return

        if event.is_directory:
            files_in_dir = [event.src_path+"/"+f for f in os.listdir(event.src_path)]
            if len(files_in_dir) > 0:
                modified_filename = max(files_in_dir, key=os.path.getmtime)
            else:
                return
            if os.path.isfile(modified_filename) and self.included(event=None, base=modified_filename):
                logging.debug("Event: modified file : %s" % self.remove_prefix(modified_filename))
        else:
            modified_filename = event.src_path
            logging.debug("Event: modified file : %s" % self.remove_prefix(modified_filename))


class LocalWatcher(threading.Thread):

    def __init__(self, local_path, includes, excludes):
        threading.Thread.__init__(self)
        self.basepath = local_path
        self.observer = None
        self.includes = includes
        self.excludes = excludes

    def stop(self):
        self.observer.stop()

    def run(self):
        event_handler = PydioEventHandler(self.basepath, self.includes, self.excludes)

        logging.info('Scanning for changes since last application launch')

        # previous_snapshot = SqlSnapshot(self.basepath)
        # snapshot = DirectorySnapshot(self.basepath, recursive=True)
        # diff = DirectorySnapshotDiff(previous_snapshot, snapshot)
        # for path in diff.dirs_created:
        #     event_handler.on_created(DirCreatedEvent(path))
        # for path in diff.files_created:
        #     event_handler.on_created(FileCreatedEvent(path))
        # for path in diff.dirs_moved:
        #     event_handler.on_moved(DirMovedEvent(path[0], path[1]))
        # for path in diff.files_moved:
        #     event_handler.on_moved(FileMovedEvent(path[0], path[1]))
        # for path in diff.files_deleted:
        #     event_handler.on_deleted(FileDeletedEvent(path))
        # for path in diff.dirs_deleted:
        #     event_handler.on_deleted(DirDeletedEvent(path))
        logging.info('Starting permanent monitor')
        self.observer = Observer()
        self.observer.schedule(event_handler, self.basepath, recursive=True)
        self.observer.start()
        while True:
            time.sleep(1)
        self.observer.join()


if __name__ == "__main__":

    logging.basicConfig(filename=os.path.dirname(os.path.realpath(__file__)) + '/filesystem.log',
                        level=logging.DEBUG,
                        format='%(asctime)s - %(message)s',
                        datefmt='%Y-%m-%d %H:%M:%S')

    parser = argparse.ArgumentParser('Pydio Framework watcher')
    parser.add_argument('-p', '--path', help='Node path', default=None)
    args, _ = parser.parse_known_args()

    watcher = LocalWatcher(args.path, includes=['*'], excludes=['.*'])

    try:
        watcher.start()
    except (KeyboardInterrupt, SystemExit):
        watcher.stop()
        sys.exit()