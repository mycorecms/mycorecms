<?php
/*  Class for rendering HTML Page
    Copyright (C) 2007-2014  MyCoreCMS

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details. <http://www.gnu.org/licenses/>.

 * HTML Page class
 * Last modified 20131229 LOLINGER
 * Create an HTML page
 * Modifications are done via function calls
 * Can be extended to include templates
 *
 * Public Member Functions:
 * __construct()
 * __destruct()
 * set_doctype(newvalue)
 * set_htmlargs(newvalue)
 * clear_head()
 * append_head(newvalue, nocr)
 * set_title(newvalue)
 * string get_title()
 * clear_content()
 * append_content(newvalue, nocr)
 * render()
 */


class HtmlClass {
	protected $doctype;
	protected $htmlargs;
	protected $title;
	protected $head;
	protected $content;
	protected $search_engine_terms;

	function __construct(&$menu = "",&$login = "") {
                  //Get the Menu
		$this->menu = $menu;
		$this->login = $login;
		// Set defaults
		$this->doctype = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\"\n \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
		$this->htmlargs = "xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\"";
		$this->meta = "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" /><meta http-equiv=\"Cache-control\" content=\"private\">";
		$this->meta.=" <meta name='description' content='MyCoreCMS is a lightweight jQuery driven CMS designed to easily build dynamic relational databases.'>";
		$this->meta .="<meta name='keywords' content='jQuery,CRM,CMS,DBMS,MyCoreCMS,Database,Administration,Management,Open Source'> ";
		$this->head = "";
		$this->analytics = "<script>
                (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
                })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
                ga('create', '".SITE_GA_KEY."', '".str_replace("www.","",$_SERVER['SERVER_NAME'])."');
                ga('send', 'pageview');</script>";
		$this->content = "";
		$this->templatedir =  SITEPATH."/view/".SITE_TEMPLATE."/";
		$this->templateurl =  "http://".$_SERVER['SERVER_NAME'].str_replace("index.php","",$_SERVER['PHP_SELF'])."view/".SITE_TEMPLATE;
		$this->title = "";
		$this->sitename = SITE_NAME;
		$this->current_year = date('Y');
	}

	function __destruct() {
	}

	// Set doctype, overwriting anything old
	public function set_doctype($newvalue) {
		$this->doctype = $newvalue;
	}

	// Set htmlargs, overwriting anything old
	public function set_htmlargs($newvalue) {
		$this->htmlargs = $newvalue;
	}

	// Clear head
	public function clear_head() {
		$this->head = "";
	}

	// Append to head, also add a CR unless nocr==true
	public function append_head($newvalue, $nocr=false) {
		$this->head .= $newvalue . ($nocr ? "" : "\n" );
	}

	// Set title, overwriting anything old
	public function set_title($newvalue) {
		$this->title = "$newvalue\n";
	}
	
	// Get current title
	public function get_title() {
		return $this->title;
	}
	
        public function show_head() {
	    echo $this->meta."\n";
		echo "<title>{$this->title}</title>\n";
		$buffer = file_get_contents($this->templatedir . "head.inc");
		echo str_replace("%TEMPLATEURL%", $this->templateurl, $buffer);
		echo "\n{$this->head}\n";
	}

	public function show_body_start() {
		$buffer = file_get_contents($this->templatedir . "body_start.inc");

		echo str_replace(
		 array("%TEMPLATEURL%",  "%h1%","%menu%","%login%","%SITE_LOGO%"),
		 array($this->templateurl, $this->title, $this->menu, $this->login,SITE_LOGO),
		 $buffer);
	}

	public function show_body_end() {
		$buffer = file_get_contents($this->templatedir . "body_end.inc");
		echo str_replace(
		 array("%TEMPLATEURL%", "%currentyear%", "%h1%", "%sitename%"),
		 array("http://".$_SERVER['SERVER_NAME'], $this->current_year,$this->title,$this->sitename),
		 $buffer);
	}

	// Clear content
	public function clear_content() {
		$this->content = "";
	}

	// Append to content, also add a CR unless nocr==true
	public function append_content($newvalue, $nocr=false) {
		$this->content .= $newvalue . ($nocr ? "" : "\n" );
	}

	// Output entire page
	function render() {
		echo $this->doctype;
		echo "<html " . $this->htmlargs . ">\n";

		echo "<head>\n";
		$this->show_head();
		echo "\n</head>\n";

		echo "<body>\n";
                IF(SITE_GA_KEY != '')
                     echo  $this->analytics;
		$this->show_body_start();
		echo "\n{$this->content}\n";
		$this->show_body_end();

		echo "\n</body>\n";
		echo "</html>\n";
	}
}
?>