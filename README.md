# Introduction

This is an KISS (Keep It Simple and Stupid) web monitor for
printrun / pronterface written in PHP.

For webcam access it requires streamer from the following package:
- xawtv

and v4l2 support in linux kernel.

For printrun querying it requires:
- httpd
- php
- php-xmlrpc
- php-fpm

These can be installed on Fedora using:
```
# dnf install xawtv httpd php php-xmlrpc php-fpm
```

It can handle unlimited number of pronterface instances and webcams.

If you run webserver on different user than root (which you really
should), you may need to allow it access to V4L devices. In case
you run Apache as apache user, just adding apache to the video
group should do the trick:
```
# gpasswd -a apache video
# service httpd restart
```

# SELinux

In case you use SELinux it will require more tweaking. At first
you need to allow Apache processes to use network sockets:
```
# setsebool -P httpd_can_network_connect 1
```

Next you need to make folder for images writeable by Apache.
Supposing that the images from the webcameras are stored in the
'img' folder (which is the default), the following commands will
create the folder and allow write operations on it:
```
# cd /var/www/html
# mkdir img data
# chgrp apache img
# chmod g+w img
# semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/data(/.*)?"
# semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/img(/.*)?"
# restorecon -Rv /var/www/html/*
```

Finally you need to create and enable SELinux module which will
allow Apache processes access to V4L devices. On Fedora the
following commands will do it:
```
# dnf install selinux-policy-devel
# mkdir apache_v4l
# cd apache_v4l
# cat > apache_v4l.te <<:EOF
module apache_v4l 1.0;

require {
  class chr_file { open read getattr write ioctl map };
  type httpd_t;
  type v4l_device_t;
};

# Enable v4l devices access by apache
allow httpd_t v4l_device_t:chr_file { open read getattr write ioctl map };
:EOF

# make -f /usr/share/selinux/devel/Makefile
# semodule -i apache_v4l.pp
```

# Development

The most of the development is done on GitHub:
https://github.com/yarda/printrun-webmon

In case you are not familiar with GitHub you can also sent
patches directly to: jskarvad AT redhat.com

# License

Copyright (C) 2016-2018 Jaroslav Škarvada <jskarvad AT redhat.com>

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 3
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
MA 02110-1301, USA.

Full text of the license is enclosed in the [LICENSE](/LICENSE) file.
