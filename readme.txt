MyCoreCMS is a lightweight jQuery driven CMS designed to easily build dynamic relational databases.
With MyCoreCMS you can build something as simple as a forum to as complex as a customer relationship management tool tailored to your specific needs.
MyCoreCMS provides a framework that many different types of sites can build upon. The idea behind MyCoreCMS is to have one CMS for all your needs.


To install, copy contents to desired web folder.
Either enter mysql connection settings in model/settings.php or access the web site and enter the connection settings.

Once the site is setup start creating pages by goto to Site->Page. 
If you are building a dynamic database you will want to select the page type of Table.
Once the page is added you can then edit it and go to the Table tab and from there you can add fields.
Once you have added all the fields go to your user account and add the page via page permissions.
You can then refresh your browser and the page will show up in your menu.

To create a component try starting with the example component in site/example.php.
Upload your component to the desired directory and then add that component via your user account page permissions.
If there are any errors in the component they will show up, if no pages or errors showup when trying to add the page you may have error checking turned off, in which case you will need to check your error log.
Once you add the page you can then refresh your browser and the page will show up in your menu.

To create a page type try starting with a copy of the page_table.php file. 
All page types must be in the site/page folder and have "page_" at the begining of the filename.
All page types must have a page_id for linking purposes.
All page types must have a load function that tells the system how to handle the page.
When a page type is uploaded to the page folder it will show up as an available option when creating a new page.

Goto http://www.mycorecms.com for support.