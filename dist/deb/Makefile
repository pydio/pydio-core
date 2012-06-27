# Very basic Makefile

DESTDIR		= /
DIST		= stable
URGENCY		= high

MAKE_OPTS	= -s -w

INSTALL		= /usr/bin/install

builddeb:
		dpkg-buildpackage -rfakeroot

checkdeb:
		lintian --checksums --color auto -v -i  ../`parsechangelog | grep ^Source | awk '{print $$2}'`_`parsechangelog | grep ^Version | awk '{print $$2}'`_*.deb

newversion:
		debchange --changelog debian/changelog -i --urgency $(URGENCY)
changelog:
		debchange --changelog debian/changelog --force-distribution $(DIST) --urgency $(URGENCY) -r
		debchange --changelog debian/changelog -a 
