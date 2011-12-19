Name:           ajaxplorer-release       
Version:        4
Release:        1
Summary:        AjaXplorer EL6 repository configuration

Group:          System Environment/Base 
License:        GPL 
URL:            http://elgis.argeo.org

Source0:        ajaxplorer.repo

BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildArch:     noarch

%description
This package contains the yum configuration for running AjaXplorer on Enterprise Linux 6.

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
* Mon Dec 19 2011 Mathieu Baudier <mbaudier@argeo.org> - 4-1
- initial version
