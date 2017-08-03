INSTALLATION STEPS (for WordPress 1.5)

1. Extract files to a folder locally.

2. [Optional] Edit paged-comments.php to configure. This step is not required -- you can always edit the file at a later time.

3. Upload paged-comments.php to your plugins folder (wp-content/plugins/).

4. Upload wp-paged-comments.php file to your wordpress root folder (the one holding index.php).

5. Edit any template files that invoke comments_template(), replacing the method call with this include statement: include(ABSPATH.'/wp-paged-comments.php');
Note: In WordPress 1.5, the comments template file is now part of each theme. So if you'd like paged comments functioning in all themes, you will have to carry out this step for all your themes.

6. Enable paged comments plugin through wordpress admin interface.

7. [Optional] Edit .htaccess if you enabled the fancy_url feature in paged-comments.php. Make sure you enter these lines:
RewriteRule ^(.+/)comment-page-([0-9]+)/?$ $1?cp=$2 [QSA,L]
RewriteRule ^(.+/)all-comments/?$ $1?cp=all [QSA,L]
at the end of the file beneath the '#END WordPress' marker (this ensures WordPress leaves the rule alone when updating the other rewrite rules.