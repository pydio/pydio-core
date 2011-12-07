%define ajaxplorerdir %{_datadir}/ajaxplorer
Name: ajaxplorer
Version:  3.3.6
Release:  0-20111206-2620%{?dist}
Summary: PHP rich-client browser for managing files on a web server

Group: Applications/Publishing
License: AGPL
URL: http://www.ajaxplorer.info
Source0: http://sourceforge.net/projects/ajaxplorer/files/ajaxplorer/%{version}/ajaxplorer-core-%{version}-20111206-2620.zip
Source1: %{name}.conf
Patch0: ajaxplorer-paths.patch

BuildArch: noarch
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
Requires: php php-xml php-gd
#Requires: php-mcrypt

%description
AjaXplorer is a PHP rich-client browser for managing files on a web server without FTP.
 Implements usual file actions, online zip browsing, text files edition and images preview.
 Users management system and multi-languages. 

%prep

%setup -q -n %{name}-core-%{version}

%patch0 -p1 -b .paths

%build

%install
rm -rf %{buildroot}

# copy application
install -d %{buildroot}%{ajaxplorerdir}
cp -pr * %{buildroot}%{ajaxplorerdir}

# apache conf
mkdir -p %{buildroot}%{_sysconfdir}/httpd/conf.d
cp -pr %SOURCE1 %{buildroot}%{_sysconfdir}/httpd/conf.d/%{name}.conf

# move conf to /etc
mv %{buildroot}%{ajaxplorerdir}/conf %{buildroot}%{_sysconfdir}/%{name}

# move doc to /usr/share/doc
mkdir -p %{buildroot}%{_datadir}/doc/%{name}
mv %{buildroot}%{_sysconfdir}/%{name}/RELEASE_NOTE %{buildroot}%{_datadir}/doc/%{name}
mv %{buildroot}%{_sysconfdir}/%{name}/VERSION %{buildroot}%{_datadir}/doc/%{name}

# move data to /var
mkdir -p %{buildroot}%{_localstatedir}/lib
mv %{buildroot}%{ajaxplorerdir}/data %{buildroot}%{_localstatedir}/lib/%{name}

# move cache to /var/cache
mkdir -p %{buildroot}%{_localstatedir}/cache
mv %{buildroot}%{_localstatedir}/lib/%{name}/cache %{buildroot}%{_localstatedir}/cache/%{name}

# move logs to /var/log
# how to configure log?
mkdir -p %{buildroot}%{_localstatedir}/log
mv %{buildroot}%{_localstatedir}/lib/%{name}/logs %{buildroot}%{_localstatedir}/log/%{name}

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,root,-)
%doc %{_datadir}/doc/%{name}/*
%{ajaxplorerdir}
%{_sysconfdir}/%{name}/.htaccess
%config(noreplace) %{_sysconfdir}/%{name}/*
%config(noreplace) %{_sysconfdir}/httpd/conf.d/%{name}*.conf
#%attr(755,root,apache) %config(noreplace) %{_sysconfdir}/cron.hourly/%{name}
%attr(755,apache,apache) %{_localstatedir}/lib/%{name}
%dir %attr(755,apache,apache) %{_localstatedir}/cache/%{name}
%dir %attr(755,apache,apache) %{_localstatedir}/logs/%{name}
%{_localstatedir}/cache/%{name}/.htaccess

%changelog
* Wed Dec 07 2011 Mathieu Baudier <mbaudier@argeo.org> - 3.3.6-0-20111206-2620
- Fix issue with logs paths

* Fri Dec 02 2011 Mathieu Baudier <mbaudier@argeo.org> - 3.3.5-1
- Initial packaging
