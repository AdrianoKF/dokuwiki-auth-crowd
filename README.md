# dokuwiki-auth-crowd
Atlassian Crowd authentication plugin for DokuWiki

## Prerequisites
In order to use the plugin, you need to install the `Services_Atlassian_Crowd` PEAR module:

```
  sudo pear install  "channel://pear.php.net/Services_Atlassian_Crowd-0.9.5"
```

## Configuration
Before you can log into Dokuwiki, you need to create an application for Dokuwiki in your Crowd administration console.
Then add the following lines to your DokuWiki config file (e.g. `local.protected.php`):

```
$conf['authtype'] = 'authcrowd';
$conf['plugin']['authcrowd']['app_name'] = 'dokuwiki';
$conf['plugin']['authcrowd']['app_password'] = '<application password for DokuWiki>';
$conf['plugin']['authcrowd']['server_url'] = 'https://avionicslab.ds-lab.org/crowd';
$conf['plugin']['authcrowd']['debug'] = 0;
```

You can enable additional diagnostic output by setting the `debug` flag to `1` in this file.

## Credits
This plugin is based on a prior implementation by Jan Schumann.
You can find his code on [GitHub](https://github.com/janschumann/dokuwiki-auth-plugin-crowd).
