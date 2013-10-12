Name:           pydio-release
Version:        1
Release:        1
Summary:        Pydio EL6 repository configuration

Group:          System Environment/Base 
License:        AGPL
URL:            http://pyd.io

Source0:        pydio.repo

BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildArch:     noarch

%description
This package contains the yum configuration for running Pydio on Enterprise Linux 6.

%prep
%setup -q  -c -T
install -pm 644 %{SOURCE0} .

%build


%install
rm -rf $RPM_BUILD_ROOT

# yum
install -dm 755 $RPM_BUILD_ROOT%{_sysconfdir}/yum.repos.d
install -pm 644 %{SOURCE0} \
    $RPM_BUILD_ROOT%{_sysconfdir}/yum.repos.d

%clean
rm -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root,-)
%config(noreplace) /etc/yum.repos.d/*


%changelog
* Sat Oct 12 2013 Charles du Jeu <charles@pyd.io> - 1-1
- pydio initial version
