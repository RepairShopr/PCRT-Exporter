PCRT-Exporter
=============

A simple script to help import your data into RepairShopr from your PCRT installation.

Right now it supports Customers/Tickets/Invoices/Assets.

It brings in your "Receipts" as invoices.

## Method 1 - Web Based 

Note: This method has proven to be unreliable on many server types, we now suggest using the new command line version.


How to use it:

1. Install it on your PCRT server (save the file to your server root)
2. Get it to be exectuable, chmod -R 777 exporter (DELETE THIS WHEN YOU ARE DONE)
3. Browse to the new file

It will ask their db host, user, password, RepairShopr subdomain and RepairShopr API key (which you can find in RepairShopr under User Menu - My Profile/Password)

So if you have pcrt like: your-pcrt-installation.yourdomain.com - you would put this in the pcrt folder, under 'exporter' - and then browse to http://your-pcrt-installation.yourdomain.com/exporter 

The upload then happens from that page!

## Method 2 - Command line (CLI)

1. Download the files here to your server, put the "exporter" folder next to your "repair" and "store" folders where your PCRT website is.
![Image of Yaktocat](http://i.imgur.com/9J0LcNK.png)
2. Make exporterCli.php executable like; ```chmod 700 exporterCli.php```
3. Delete the config file, running the script will create one; ```rm configCli.php```
4. Run the program with php like; ```php exporterCli.php```
5. It will ask your api key and subdomain, then give you a menu. ALWAYS start with customers, that will put identifiers for RepairShopr reference in your database - and only after uploading your customers can you continue to invoices/tickets/assets.
