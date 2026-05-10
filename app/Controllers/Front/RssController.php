<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Core\Config;
use LyBlog\Models\Article;

class RssController extends BaseController
{
    public function index(Request $request)
    {
        $siteName = Config::get('site_name', 'LyBlog');
        $siteUrl  = rtrim(Config::get('site_url', '/'), '/');
        $siteDesc = Config::get('site_description', '');

        $data = Article::getPublished(1, 20);

        header('Content-Type: application/rss+xml; charset=utf-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo "  <channel>\n";
        echo "    <title>" . htmlspecialchars($siteName, ENT_XML1, 'UTF-8') . "</title>\n";
        echo "    <link>" . htmlspecialchars($siteUrl, ENT_XML1, 'UTF-8') . "</link>\n";
        echo "    <description>" . htmlspecialchars($siteDesc, ENT_XML1, 'UTF-8') . "</description>\n";
        echo "    <language>zh-CN</language>\n";
        echo "    <lastBuildDate>" . date('r') . "</lastBuildDate>\n";
        echo "    <atom:link href=\"{$siteUrl}/rss\" rel=\"self\" type=\"application/rss+xml\"/>\n";

        foreach ($data['items'] as $article) {
            $link = $siteUrl . '/article/' . $article['slug'];
            echo "    <item>\n";
            echo "      <title>" . htmlspecialchars($article['title'], ENT_XML1, 'UTF-8') . "</title>\n";
            echo "      <link>" . htmlspecialchars($link, ENT_XML1, 'UTF-8') . "</link>\n";
            echo "      <guid isPermaLink=\"true\">" . htmlspecialchars($link, ENT_XML1, 'UTF-8') . "</guid>\n";
            echo "      <pubDate>" . date('r', strtotime($article['published_at'])) . "</pubDate>\n";
            if ($article['excerpt']) {
                echo "      <description>" . htmlspecialchars($article['excerpt'], ENT_XML1, 'UTF-8') . "</description>\n";
            }
            if ($article['cover_image']) {
                echo "      <enclosure url=\"" . htmlspecialchars($article['cover_image'], ENT_XML1, 'UTF-8') . "\" type=\"image/jpeg\"/>\n";
            }
            echo "    </item>\n";
        }

        echo "  </channel>\n";
        echo "</rss>";
        exit;
    }
}
