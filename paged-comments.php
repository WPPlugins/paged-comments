<?php 
/*
Plugin Name: Paged Comments
Plugin URI: http://www.keyvan.net/code/
Description: Breaks down comments into a number of pages 
Author: Keyvan Minoukadeh
Contributors: Brian Dupuis
Version: 2005-05-20
Author URI: http://www.keyvan.net/
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/ 

// ===============================================================
// ====================== START EDITING ==========================

// Enable paged comments on all posts?
// -----------------------------------
// Setting this to false will disable paged comments by default.
// Individual posts can still enable paged comments by adding a custom
// field: 'paged_comments' with the value 'on'.
// Note: to disable paged comments completely, deactivate this plugin
// through the admin interface.
$paged_comments->all_posts = true; 

// Comments per page
// -----------------
// Page numbers will only be displayed when comments exceed this value
// Individual posts can override this value by adding a custom field with
// 'comments_per_page' as key.
$paged_comments->per_page = 10;

// Comment ordering
// ----------------
// 'ASC': earliest comments will be displayed first and page numbers increase from 1: 1,2,3,...x
// 'DESC': latest comments will be displayed first and page numbers will decrease from x: x,....3,2,1
// Note: ordering is implemented this way so new comments don't 
// displace older comments on a page.
// Individual posts can override this value by adding a custom field with
// 'comment_ordering' as key.
$paged_comments->ordering = 'DESC';

// Page range
// ----------
// Number of page numbers to show at one time.
// e.g. if there are 10 pages, current page is page 6 and page range is 5
// page numbers displayed will be: << 4 5 (6) 7 8 >>
$paged_comments->page_range = 11;

// Fancy URL
// ---------
// If you currently have a custom URI structure for permalinks 
// (see: <http://faq.wordpress.net/view.php?p=20>), enabling
// this will append /comment-page-x/ (where x is a page number) to the end 
// of the URLs for comment pages.
// Note: you MUST edit your WordPress .htaccess file prior to enabling this.
// Enter the following lines:
// RewriteRule ^(.+/)comment-page-([0-9]+)/?$ $1?cp=$2 [QSA,L]
// RewriteRule ^(.+/)all-comments/?$ $1?cp=all [QSA,L]
// at the end of the .htaccess file beneath the #END WordPress marker 
// (this ensures WordPress leaves the rule alone when updating the other rewrite rules.
$paged_comments->fancy_url = false;

// Show all comments option
// ------------------------
// If enabled, visitors will have the option of choosing to see all
// comments on one page (ie. not paged). A 'show all' link will be 
// displayed if this is enabled.
$paged_comments->show_all_option = true;

// ======================= END EDITING ===========================
// ===============================================================

// override default values with custom post values
function paged_comments_update_values()
{
    global $paged_comments;
    // comments per page
    $val = paged_comments_get_custom('comments_per_page');
    if (!empty($val)) $paged_comments->per_page = (int)$val;
    // comment ordering
    $val = strtoupper(paged_comments_get_custom('comment_ordering'));
    if (($val == 'ASC') || ($val == 'DESC')) $paged_comments->ordering = $val;
}

function paged_comments_get_custom($field)
{
    $val = @get_post_custom_values($field);
    return trim(@$val[0]);
}

// returns true if paged comments are enabled for this post
function paged_comments()
{
    global $paged_comments;
    // paged comments only when viewing a single post or a single page
    if (!is_single() && !is_page()) return false;
	// has user chosen to view all comments?
	// if so, disable paged comments for this post
    if(!(strpos( $_GET['cp'], 'all' )===false) && $paged_comments->show_all_option) return false;
    // is paging enabled for all posts?
    if ($paged_comments->all_posts) return true;
    // has paging been explicitly enabled for this post
    $paging_enabled = get_post_custom_values('paged_comments');
    if (@$paging_enabled[0] == 'on') return true;
    // paging not enabled for this post
    return false;
}

// initialise pager
function paged_comments_init_pager($total_comments)
{
    global $paged_comments;
    paged_comments_update_values();
    $paged_comments->main_pager =& new Pager($paged_comments->per_page, $total_comments);
    $paged_comments->pager =& $paged_comments->main_pager;
    if ($paged_comments->ordering == 'DESC') {
        $paged_comments->pager =& new InvertedPager($paged_comments->pager);
    }
    // set page number
    $page = (int)@$_GET['cp'];
    if ($page > 0) $paged_comments->pager->set_current_page($page);
}

// for mysql LIMIT clause (returns array with offset and limit)
function paged_comments_sql_limit()
{
    global $paged_comments;
    $remainder = $paged_comments->pager->get_total_items() % $paged_comments->per_page;
    $offset = ($paged_comments->main_pager->get_current_page() - 1) * $paged_comments->per_page;

    // limit clause for comments in ascending order (or if total-comments multiple of comments-per-page)
    if (($paged_comments->ordering == 'ASC') || ($remainder == 0)) {
        return array($offset, $paged_comments->per_page);
    }
    // limit clause for comments in descending order (if we're on the last page)
    if ($paged_comments->pager->get_current_page() == $paged_comments->pager->num_pages()) {
        return array(0, $remainder);
    } else {
        return array($offset + $remainder - $paged_comments->per_page, $paged_comments->per_page);
    }
}

// output page numbers
function paged_comments_print_pages()
{
    global $paged_comments, $id, $post;
    // Wordpress 1.3-alpha-2 uses $post to hold post details (such as id)
    if (isset($post->ID)) $id = $post->ID;
	$qparam = is_page() ? 'page_id' : 'p';
    if ($paged_comments->fancy_url && (get_settings('permalink_structure') != '')) {
        $url = rtrim(get_permalink(), '/')."/comment-page-%u/#comments";
        $allurl = rtrim(get_permalink(), '/')."/all-comments/#comments";
    } else {
        $url = get_settings('siteurl').'/'.get_settings('blogfilename').'?'.$qparam.'='.$id.'&amp;cp=%u#comments';
        $allurl = get_settings('siteurl').'/'.get_settings('blogfilename').'?'.$qparam.'='.$id.'&amp;cp=all#comments';
    }
    $printer =& new PagePrinter($paged_comments->pager, $url, $paged_comments->page_range);
    $left = '&laquo;'; $right = '&raquo;'; $older = 'Older comments'; $newer = 'Newer comments'; $sep = ' ';
    $link_left = ($paged_comments->ordering == 'ASC') ? $printer->get_prev_link($left, $older) : $printer->get_next_link($left, $newer);
	// left arrow link
    if (!empty($link_left)) echo $link_left, $sep;
	// page number links
    echo $printer->get_links($sep);
	// right arrow link
    $link_right = ($paged_comments->ordering == 'ASC') ? $printer->get_next_link($right, $newer) : $printer->get_prev_link($right, $older);
    if (!empty($link_right)) echo $sep, $link_right;
    if ($paged_comments->show_all_option) echo $sep, '(<a href="'.$allurl.'">Show All</a>)';
}

// The classes below are used to calculate page numbers and print pages numbers

/*****************************************
* Class: Pager 
* Originally by: Tsigo <tsigo@tsiris.com>
* Modified: Keyvan
* Redistribute as you see fit. 
*****************************************/
class Pager 
{
    /**
    * Items per page.
    *
    * This is used, along with <var>$item_total</var>, to calculate how many
    * pages are needed.
    * @var int
    */
    var $items_per_page;

    /**
    * Total number of items 
    *
    * This is used, along with <var>$items_per_page</var>, to calculate how many
    * pages are needed.
    * @var int
    */
    var $item_total;

    /**
    * Current page
    * @var int
    */
    var $current_page;
  
    /**
    * Number of pages needed
    * @var int
    */
    var $num_pages;

    /**
    * Constructor
    */
	function Pager($items_per_page, $item_total)
	{
        $this->items_per_page = $items_per_page;
        $this->item_total = $item_total;
        $this->num_pages = (int)ceil($this->item_total / $this->items_per_page);
        $this->set_current_page(1);
	}

    /**
    * Set current page number
    * @param int $page
    */
    function set_current_page($page)
    {
		$this->current_page = min($page, $this->num_pages());
		$this->current_page = max($this->current_page, 1);
    }

    /**
    * Get current page
    * @return int
    */
    function get_current_page()
    {
        return $this->current_page;
    }

    /**
    * Get items per page
    * @return int
    */
    function get_items_per_page()
    {
        return $this->items_per_page;
    }

    /**
    * Get total items
    * @return int
    */
    function get_total_items()
    {
        return $this->item_total;
    }
    
    /**
    * Number of pages needed
    * @return int
    */
    function num_pages() 
    {
        return $this->num_pages;
    }

    /**
    * Is last page
    * @return boolean
    */
    function is_last_page()
    {
        return ($this->get_current_page() == $this->num_pages());
    }

    /**
    * Is first page
    * @return boolean
    */
    function is_first_page()
    {
        return ($this->get_current_page() == 1);
    }

    /**
    * Get page numbers within range
    * @param int $page_range number of pages to display at one time, default: all pages
    * @return array
    */
    function get_page_numbers($page_range=null)
    {
        if (!isset($page_range)) {
            return range(1, $this->num_pages());
        } else {
            // set boundaries
            $pages = $this->num_pages();
            $range_halved = (int)floor($page_range / 2);
            $count_start = $this->current_page - $range_halved;
            $count_end = $this->current_page + $range_halved;

            // adjust boundaries
            while ($count_start < 1) {
                $count_start++;
                $count_end++;
            }
            while ($count_end > $pages) {
                $count_end--;
                $count_start--;
            }
            $count_start = max($count_start, 1);
            return range($count_start, $count_end);
        }
    }
}

// Implements the Pager interface but inverts numbers. (Decorator pattern)
class InvertedPager
{
    var $pager;

    function InvertedPager(&$pager)
    {
        $this->pager =& $pager;
    }

    function _invert_page($page)
    {
        return $this->pager->num_pages() + 1 - $page;
    }

    /**
    * Set current page number
    * @param int $page
    */
    function set_current_page($page)
    {
		$this->pager->set_current_page($this->_invert_page($page));
    }

    /**
    * Get current page
    * @return int
    */
    function get_current_page()
    {
        return $this->_invert_page($this->pager->get_current_page());
    }

    /**
    * Get page numbers within range
    * @param int $page_range number of pages to display at one time, default: all pages
    * @return array
    */
    function get_page_numbers($page_range=null)
    {
        return array_map(array(&$this, '_invert_page'), $this->pager->get_page_numbers($page_range));
    }

    /**
    * Get items per page
    * @return int
    */
    function get_items_per_page()
    {
        return $this->pager->get_items_per_page();
    }
   
    /**
    * Get total items
    * @return int
    */
    function get_total_items()
    {
        return $this->pager->get_total_items();
    }

    /**
    * Number of pages needed
    * @return int
    */
    function num_pages() 
    {
        return $this->pager->num_pages();
    }

    /**
    * Is last page
    * @return boolean
    */
    function is_last_page()
    {
        return ($this->get_current_page() == $this->num_pages());
    }

    /**
    * Is first page
    * @return boolean
    */
    function is_first_page()
    {
        return ($this->get_current_page() == 1);
    }
}

// Prints page number links using a Pager instance
class PagePrinter
{
    var $pager;

    /**
    * URL formatting string for building page links
    *
    * This should be a formatting string which will be passed to sprintf()
    * (see: <http://uk.php.net/sprintf>), it should include 1 conversion 
    * specifications: %u (to hold the page number)
    * @string
    */
    var $url;

    /**
    * Number of pages to show at one time
    * @var int
    */
    var $page_range;

    function PagePrinter(&$pager, $url='', $page_range=null)
    {
        $this->pager =& $pager;
        $this->set_page_range($page_range);
        $this->set_url($url);
    }

    function get_prev_link($text='&laquo;', $title='Previous Page')
    {
        if ($this->pager->is_first_page()) return '';
        return '<a href="'.$this->get_url($this->pager->get_current_page() - 1).'" title="'.$title.'">'.$text.'</a>';
    }

    function get_next_link($text='&raquo;', $title='Next Page')
    {
        if ($this->pager->is_last_page()) return '';
        return '<a href="'.$this->get_url($this->pager->get_current_page() + 1).'" title="'.$title.'">'.$text.'</a>';
    }

    /**
    * Get page links
    * @return string HTML
    */
    function get_links($separator=' ', $pre_cur_page='<strong>[', $post_cur_page=']</strong>')
    {
        $pages = $this->pager->num_pages();
        $page_links  = ''; 
       
        // print page numbers
        $cur_page = $this->pager->get_current_page();
        $num_links = array();
        $page_numbers = $this->pager->get_page_numbers($this->page_range);
		$asc = ($page_numbers[0] < $page_numbers[1]);
        if( $asc ) {
            if( $page_numbers[0] != 1 ) {
                $num_links[] = '<a href="'.$this->get_url(1)."\">1</a> &#8230;"; 
            }
        } else {
            if( $page_numbers[0] != $this->pager->num_pages() ) {
                $num_links[] = '<a href="'.$this->get_url($this->pager->num_pages())."\">".$this->pager->num_pages()."</a> &#8230;"; 
            }
        }
        foreach ( $page_numbers as $i) { 
            if ($i == $cur_page) { 
                $num_links[] = $pre_cur_page.$i.$post_cur_page; 
            } else { 
                $num_links[] = '<a href="'.$this->get_url($i)."\">$i</a>"; 
            } 
        } 
        if( $asc ) {
            if( $page_numbers[count($page_numbers)-1] != $this->pager->num_pages() ) {
                $num_links[] = '&#8230; <a href="'.$this->get_url($this->pager->num_pages())."\">".$this->pager->num_pages()."</a>"; 
            }
        } else {
            if( $page_numbers[count($page_numbers)-1] != 1 ) {
                $num_links[] = '&#8230; <a href="'.$this->get_url(1)."\">1</a>"; 
            }
        }
        $page_links .= implode($separator, $num_links);
        
        return $page_links; 
    }

    /**
    * Set page range
    * @param int $max
    */
    function set_page_range($max)
    {
        $this->page_range = $max;
    }

    /**
    * Set URL
    * @param string $url
    */
    function set_url($url)
    {
        $this->url = $url;
    }

    /**
    * Get formatted URL (including page number)
    * @param int $page page number
    * @return string
    */
    function get_url($page)
    {
        return sprintf($this->url, $page);
    }
}
?>