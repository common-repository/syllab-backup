=== SylLab Backup – HIPAA, GDPR, CCPA Framework ===
Contributors: SylLab
Tags: backup, database backup, wordpress backup, backups, compliance, GDPR, CCPA, privacy
Requires at least: 3.2
Tested up to: 5.8
Stable tag: 1.0.2
Author URI: https://syllab.io/
License: GPLv3 or later

== Description ==

Compliant backup with SylLab. We built a plugin that helps WordPress users to streamline backup compliance with the easy-to-use plugin. HIPAA, GDPR, CCPA require routine data backup to encrypted storage, and we made that easy with SylLab Backup. Ransomware attacks are becoming more vicious. Every and 39 seconds, there is a devastating cyberattack. No backup means the risk of losing your website and exposing your business to non-compliance fines. Mitigate that risks with an easy-to-use and affordable solution.      

= Secure Backup that is HIPAA, GDPR, CCPA Compliant  =

SylLab Backup – HIPAA, GDPR, CCPA Framework. Your backups are an important asset of your website that you need for security and compliance. If your company gets hacked and falls into a ransomware attack, the content of your website will be compromised, and without a regular backup, you risk losing access. Would you entrust all your hard work to one provider or your hosting that is not encrypting your data?  HIPAA, GDPR, CCPA frameworks require that your business regularly backup data. Setting up a reliable backup plugin that works consistently across the range of WordPress deployments is hard. Your backup is safe with our SylLab Vault that encrypts all backup data adding a layer of high-grade security.

= Why you should backup your website. =

Regular backup is mandatory by many regulatory frameworks. In case of an audit, your company will have to provide evidence of secure backup. SylLab Backup Plugin offers an efficient way for safe storage. WordPress can be vulnerable to hacking, server crashes, bad plugin or theme updates, and insecure web hosts. If anything happened to your website, it would take a significant amount of time, and there might be financial consequences.
Security measures are essential. Backups are crucial for security and compliance. With SylLab Backup In the worst-case scenario, your website (plus all related files and databases) stay safe and can be restored at any time.
Manual backups are available - but using backup plugin backup can set you peace of mind. With SylLab, you can set routine backup, scheduling in automatic backups to save you time.

= How SylLab compares with other backup plugins: =

We provide high-grade security and compliant backup at a fair price. The plugin is not only comprehensive in terms of its features, but it's also easy and intuitive to use. The plugin is well-tested and ready to serve a vast number of users. 

Unlike many other plugins, SylLab Backup:

* HIPAA ready – BAA Agreement 
* GDPR ready - Data Processing Agreement
* CCPA ready - Addendum
* Extensive logs
* Back up to SylLab Vault - secure and encrypted space
* Automatic backup schedules
* Faster and more efficient

== DISCLAIMER ==
Using The SylLab Backup – HIPAA, GDPR, CCPA Framework does NOT guarantee compliance to HIPAA, GDPR, CCPA. This plugin gives you general information and tools but is NOT meant to serve as a complete compliance package. 
Compliance with GDPR is a risk-based ongoing process that involves your whole business. Please refer to SylLab Terms and Conditions and Privacy Policy for more information available at our website: syllab.io


== SCREENSHOTS == 
 
1. Manual backup and backup management 
2. Settings - login, connect SylLab Vault and save changes

== Frequently Asked Questions ==

= What exactly does SylLab Backup do? =

We built a plugin that helps to backup data routinely. Set it up once and link SylLab Vault to enjoy peace of mind that your website is securely saved, and you can restore it at any time. 
Our free version of SylLab is fully functional: it performs full, manual, or scheduled backups of all your WordPress files, databases, plugins and themes, and restores them direct from your WordPress control panel.

All paid versions include:
* Splitting your website into multiple archives
* Downloads backup archives directly from your WordPress and SylLab Vault dashboard
* Support Ticketing System
* Downloading encrypted and decrypted files
* SylLab Vault to upload and download individual files    
* The plugin is supported on all current versions of PHP
* SaaS interface
* Direct login and link through WordPress to SylLab remote storage 

Our basic paid version:
* 100 MB of storage
* Dashboard logs


= What are the benefits of HIPAA Premium? =
HIPAA is the United States federal law that requires protecting sensitive health information. The law mandates regular backup of data. In case of an audit, your business will be required to demonstrate a backup system. SylLab makes it easy and accessible for your business at an affordable price.  

Premium HIPAA Backup is a perfect solution for practitioners and healthcare providers:
* 1 GB of storage
* BAA Agreement
* Dashboard logs

= What are the benefits of GDPR Premium? =
Premium GDPR Backup is a perfect solution for companies processing data of EU residents:
* 1 GB of storage
* Data Processing Agreement
* Dashboard logs

= What are the benefits of CCPA Premium? =
Premium GDPR Backup is a perfect solution for companies processing data of California residents:
* 1 GB of storage
* Data Processing Agreement
* Dashboard logs


= If my site gets hacked and the backups don't work, is there anything I can do? =
Unfortunately not; since this is free software, there's no warranty and no guarantee. It's up to you to verify that SylLab is creating your backups correctly. The most secure way is to backup your data with SylLab Vault. 
= How to install SylLab Backup? =
You can find more information and guidelines on our website. 

= I need support, how can I reach your team? =
After registering at our website, you can use Contact Us to message us directly. You will be able to track your ticket, and we will help you to resolve the issues. 

== License ==

The plugin code, syllab-backup, limited to this WordPress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus

What is new and different:
The plugin's main functionality is backup to SylLab Vault, which is a separate system linked with API. UpdraftPlus is not providing this functionality, and their integrations are with several different other third-party providers like Google Drive, Dropbox, One Drive, Azure ect. The reason why it's significant is that backup to SylLab Vault is the main functionality of the SylLab plugin. Unlike UpdraftPlus, our plugin is not providing: Cloning and migration, Network Multisite, Reporting, Pre-update backups, Importer, Run from WP-CLI. The fork we made did not include SylLab Vault integration, and we developed that on our end.  We give the full accreditation to the code we forked in the heading of each of the files. 

Changes to the fork:
Backing up files to Syllab Vault: Users can back up their files to local storage and remote storage. We provide Syllab Vault as remote storage. After successful connection using correct credentials( username and password ), the user's file will be stored into Syllab Vault. SylLab Vault is a proprietary software connected with the plugin via API.      

HTTP API (removing curl) : We have used WordPress HTTP API method like wp_remote_get() and wp_remote post() to send data to Syllab API. There is no use of CURL for this purpose. 

Sanitization ( Securing Input ) :  Sanitization is the process of cleaning or filtering user input or data from API end.  We have used sanitize_*() series of helper functions to ensure data safety. We use sanitize_text_field() , sanitize_title() etc.

Escaping ( Securing Output ): Escaping is the process of securing output by stripping out unwanted data, like malformed HTML or script tags, preventing this data from being seen as code. We have used helper function of WordPress like esc_html() , esc_url() and esc_attr() etc to escape output data .

UpdraftPlus Files and Functionalities Removed: We have removed all files of UpdraftPlus that are not necessary for our scope. UpdraftPlus provides backup and storage in many third-party storage like Google Drive , AWS S3 Storage etc, but we are providing only one third-party storage that is Syllab Vault. So, we have removed all files that are not necessary for our scope.

Fork contribution acknowledgment: We have included a reference in all files that are forked from UpdraftPlus source code (Version 1.11.3) [Source code]. The text is " The plugin code, syllab-backup, limited to this WordPress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ is a fork of DavidAnderson (2015) source code (Version 1.11.3) [Source code]. https://github.com/wp-plugins/updraftplus. 
The other contributors and developers of the [Source code] cited here (Version 1.11.3):
https://profiles.wordpress.org/backup-with-updraftplus/,  https://profiles.wordpress.org/dnutbourne/, https://profiles.wordpress.org/snightingale/, https://profiles.wordpress.org/aporter/, https://profiles.wordpress.org/bcrodua/ " .    

This fork syllab-backup, limited to this WordPress public repository: https://plugins.trac.wordpress.org/browser/syllab-backup/ with changes made by SylLab is licensed under Free Software Foundation and a copyright of SylLab Systems, Inc. 

    Copyright 2021-30 SylLab Systems, Inc.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Depending on any non-English translation is at your own risk. SylLab can give no guarantees that translations from the original English are accurate.