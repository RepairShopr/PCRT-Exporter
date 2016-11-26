PCRT-Importer
=============

A simple script to help import your data into RepairShopr from your PCRT installation.

Right now it supports Customers/Tickets/Invoices/Assets.

It brings in your "Receipts" as invoices.

## Command line (CLI)

1. Download the files here to your server, put the "exporter" folder next to your "repair" and "store" folders where your PCRT website is.
![Image of Yaktocat](http://i.imgur.com/9J0LcNK.png)
2. Make exporterCli.php executable like; ```chmod 700 exporterCli.php```
3. Delete the config file, running the script will create one; ```rm configCli.php```
4. Run the program with php like; ```php exporterCli.php```
5. It will ask your api key and subdomain, then give you a menu. ALWAYS start with customers, that will put identifiers for RepairShopr reference in your database - and only after uploading your customers can you continue to invoices/tickets/assets.
