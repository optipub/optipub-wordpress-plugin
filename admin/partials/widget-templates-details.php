<?php
defined('ABSPATH') or die('Direct access not allowed');
?>

<p><?php esc_html_e('Widget Files included:', 'optipub'); ?></p>
<ul class="details-section-lists">
    <li>
        <strong>
            <span class="dashicons dashicons-arrow-down"></span>
            <a href="#" class="file-details-toggle" data-toggle-target="#widget-latest-articles">
                optipub_widget_latest_articles.php
            </a>
        </strong> - sidebar widget
    </li>
    <div id="widget-latest-articles" class="details-section-dropdown">
        <ul style="list-style: circle;">
            <li>display the latest 5 articles, and featured articles synced from OptiPub.</li>
        </ul>
    </div>

    <li>
        <strong>
            <span class="dashicons dashicons-arrow-down"></span>
            <a href="#" class="file-details-toggle" data-toggle-target="#widget-related-articles">
                optipub_widget_related_articles.php
            </a>
        </strong> - sidebar widget
    </li>
    <div id="widget-related-articles" class="details-section-dropdown">
        <ul style="list-style: circle;">
            <li>Editor page</li>
            <ul style="list-style: inside;">
                <li>Display the latest 10 articles from the editor</li>
            </ul>
            <li>Articles pages</li>
            <ul style="list-style: inside;">
                <li>If the articles has any Tags in OptiPub, display the latest 5 articles that share any OptiPub Tags.</li>
                <li>If the article has no tags, display the latest 5 articles and featured articles.</li>
            </ul>
        </ul>
    </div>

    <li>
        <strong>
            <span class="dashicons dashicons-arrow-down"></span>
            <a href="#" class="file-details-toggle" data-toggle-target="#widget-page-toolbar">
                optipub_widget_page_toolbar.php
            </a>
        </strong>
        <span style="color: green;">configure</span> - fixed position widget
    </li>
    <div id="widget-page-toolbar" class="details-section-dropdown">
        <ul style="list-style: circle;">
            <li>
                <p>Adds a fixed widget to the right edge middle of the page, with image links to a facebook and twitter profile and a print and contact page link.</p>
                <p>You must configure the profile, image, and contact links in the widget file. Download the images from assets.optipub.com and add to your own OptiPub Assets (recommended).</p>
            </li>
            <li>
                <p>FontAwesome is required for the contact and print icons. You can download it from <a href="https://fontawesome.com/download" target="_blank">https://fontawesome.com/download</a> and host it locally (recommended).</p>
                <p>Or link to one of (all.css, all.min.css, fontawesome.css, or fontawesome.min.css) from an external cdn host on <a href="https://cdnjs.com/libraries/font-awesome" target="_blank">https://cdnjs.com/libraries/font-awesome</a> or any other host.</p>
            </li>
        </ul>
    </div>
</ul>
<p></p>
<p>These templates use the Bootstrap Framework for CSS. You will need to use Bootstrap or change the classes to fit your theme or framework css classes.</p> 