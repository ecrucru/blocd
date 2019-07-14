# Blocklist of commercial domains

This cockpit connects to your defined mailboxes via IMAP protocol and retrieves the senders of your spams. The extracted domains are put into a MySQL database for your manual validation. Then few files usable for blacklisting purposes are generated.


## Install the PHP cockpit

- Create an empty MySQL database and its user
- Copy the file `config.sample.php` to `config.php`
- Put the MySQL credentials into the file `config.php`
- Visit Blocd from a web-browser to complete the setup and use the cockpit


## Install the blocklist for Postfix

Most of the following steps require to be logged in as root.

Once :

- `vim /etc/postfix/main.cf`
- `smtpd_sender_restrictions = check_sender_access pcre:/etc/postfix/blocklist_postfix`

Periodically :

- `git pull`
- `cp blocklist_postfix /etc/postfix/`
- `postfix reload`
