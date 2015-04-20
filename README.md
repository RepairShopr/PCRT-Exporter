PCRT-Exporter
=============

A simple script to help import your data into RepairShopr from your PCRT installation.

Right now it supports Customers and Tickets.

(invoices will be coming soon)

How to use it:

1. Install it on your PCRT server (save the file to your server root)
2. Get it to be exectuable, chmod -R 777 exporter (DELETE THIS WHEN YOU ARE DONE)
3. Browse to the new file

It will ask their db host, user, password, RepairShopr subdomain and RepairShopr API key (which you can find in RepairShopr under User Menu - My Profile/Password)

So if you have pcrt like: your-pcrt-installation.yourdomain.com - you would put this in the pcrt folder, under 'exporter' - and then browse to http://your-pcrt-installation.yourdomain.com/exporter 

The upload then happens from that page!
