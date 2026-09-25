After having used start.me for a couple of years, the terms have changed for the free version of it. My main issue was that the amount of open tabs were redused and a message popped up that i needed to reduse them.

Eventually i thought: What they can do I can do also. Not a programmer, but with the use of VScode and copilot I vibecoded my own version. Yes vibecoded! Like i said, not a programmer, but with vibecoding i got to a working version that does the trick for me. Hopefully you can use it also.

What are the tools needed:
- MariaDB or Mysql Database
- PHP

Make sure you have an SSL certificate for your domain if you want to edit the links from over the internet!

What to do:
- create a database and load the structure.sql from the install folder
- Create a user with minimal rights (I've given the user Delete, Insert, Select and Update rights (My origin is DBA))
- Now rename under the folder config the file config_empty.php to config.php and change the fields according to your environment)
- Then rename under the folder config the file auth_empty.php to auth.php and edit the rows that matter. Please not that this application asks for a user and password but is a single user.
Now it's up and running

How to add links.
- Go to /admin and login.
- First go to Tabs. Give your first tab a name. There is an option you can check "Hide from public". If you are not logged in, this tab will not be shown.
- Then go to header, select the tab you want a header added, give the header a name, enter a number between 1 and 4 for the column location (you have 4 columns maximum) and give it a row number. If 2 headers have the same column number and row number, those locations will be sorted alphabetically. Click add.
- Now you can go to links. Select the tab and header under which the link will be added. The only think you need to do is add a link in the URL field and click add record. The script tries to retrieve the title from the link and the favicon from the site and stores is.
- You can edit everything from the dashboard

What features have i implemented?
- When you login a timer log you out after 15 minutes, just to be save. When you check the "Disable auto-logout" it will disable it and you have to log out yourself.
- Focus mode: When you click on a tab with the right mouse button, you can focus on that tab. All other tabs will be hidden until you click on the tab again and select unfocus. Handy for when you have a lot of tabs, but you only want one tab open for work for example.
- Edit and delete link: When right click on a link, you get the option to edit the link (maybe change the page name) or delete the link. When you delete a link, you are asked to enter a randomly generated number, just to confirm that you aren't trying to delete the link by mistake.

What features would be nice to have:
- A more dynamic way to move the headers in the columns and rows.
- Plugin for chrome/firefox/edge (It's really low on my list)
- Maybe multi user (also very low on the list)
