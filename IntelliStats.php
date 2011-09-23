<?php
/*
Plugin Name: IntelliStats
Plugin URI: http://markcahill.net/portfolio/intellistats/
Description: Provides enterprise-grade stats for your blog, including geolocation services provided by <a href='http://www.maxmind.com/app/geolitecity'>MaxMind</a> (updated 8/1/08). Provides drilldown to the IP level.
Version: 0.0.4
Author: Mark Cahill
Author URI: http://www.markcahill.net
*/

//The search terms and spiders functions are from StatPress (http://www.irisco.it/?page_id=28)
//Gotta give credit where credit is due. I just needed a different set of analytics
function intstGetQueryPairs($url){
$parsed_url = parse_url($url);
$tab=parse_url($url);
$host = $tab['host'];
if(key_exists("query",$tab)){
 $query=$tab["query"];
 return explode("&",$query);
}
else{return null;}
}

function intstSearchTerms($referrer = null){
	$key = null;
	$lines = file(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__)).'/searchengines.dat');
	foreach($lines as $line_num => $se) {
		list($nome,$url,$key)=explode("|",$se);
		if(strpos($referrer,$url)===FALSE) continue;
		# trovato se
		$variables = intstGetQueryPairs($referrer);
		$i = count($variables);
		while($i--){
		   $tab=explode("=",$variables[$i]);
			   if($tab[0] == $key){return ($nome."|".urldecode($tab[1]));}
		}
	}
	return null;
}

function intstSpider($agent = null){
    $agent=str_replace(" ","",$agent);
	$key = null;
	$lines = file(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__)).'/spider.dat');
	foreach($lines as $line_num => $spider) {
		list($nome,$key)=explode("|",$spider);
		if(strpos($agent,$key)===FALSE) continue;
		# trovato
		return $nome;
	}
	return null;
}

function intstGeolocate($ip) {
include(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__)). "/geolocation/geoipcity.inc");
include(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__)). "/geolocation/geoipregionvars.php");
$gi = geoip_open(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__)). "/geolocation/GeoLiteCity.dat",GEOIP_STANDARD);
$record = geoip_record_by_addr($gi,$ip);
return $record;
}

function intstAppend() {
global $wpdb;
$tablename = $wpdb->prefix . "intellistats";
$ip = $_SERVER['REMOTE_ADDR'];
$page = $_SERVER['REQUEST_URI'];
$referer = $_SERVER['HTTP_REFERER'];
$referer_info = parse_url($_SERVER['HTTP_REFERER']);
$referer_domain = $referer_info['host'];
list($searchengine,$search_terms)=explode("|",intstSearchTerms($referer)); //Find search terms (Statpress function)
	//Geolocation
	$record = intstGeolocate($ip);
	$country = $record->country_name;
$useragent = $_SERVER['HTTP_USER_AGENT'];
$spider = intstSpider($_SERVER['HTTP_USER_AGENT']); //Find spiders and bots and remove them from results (Statpress function)
$exclusions = file_get_contents(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__))."/exclusions.dat");
if ($spider=='' AND strpos($exclusions, $ip)===false) { $result = mysql_query("INSERT INTO $tablename VALUES(NULL, NOW(), '$ip', '$page', '$referer', '$referer_domain', '$search_terms', '$useragent', '$country');") or die(mysql_error()); }
}

function intstActivate() {
global $wpdb;
$tablename = $wpdb->prefix . "intellistats";
mysql_query("CREATE TABLE IF NOT EXISTS $tablename (visit_id INT AUTO_INCREMENT PRIMARY KEY, timestamp DATETIME, ip VARCHAR(15), page VARCHAR(255), referer VARCHAR(255), referer_domain VARCHAR(255), search_terms VARCHAR(255), useragent VARCHAR(255), country VARCHAR(55))") or die("Could not create Simple Stats table");
if (file_exists(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__))."/exclusions.dat")===FALSE) {
	$handle = fopen(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__))."/exclusions.dat","w");
	fclose($handle);
	}
mail("mark@markcahill.net","Intellistats activated on " . $_SERVER['SERVER_NAME'],"Host: " . $_SERVER['SERVER_NAME'] . "     IP of user: " . $_SERVER['REMOTE_ADDR'],"From: " . get_bloginfo('admin_email'));
}

function intstOptionsMenu() {
if ($_POST) {
	if ($_POST['save']) {
		$handle = fopen(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__))."/exclusions.dat", "w");
		$ips = strip_tags($_POST['ips']);
		fwrite($handle, $ips);
		fclose($handle);
		echo '<div style="background-color: rgb(207, 235, 247);" id="message" class="updated fade"><p><strong>Options saved.</strong></p></div>';
	}
	if ($_POST['reset']) {
		global $wpdb;
		$tablename = $wpdb->prefix . "intellistats";
		mysql_query("DELETE FROM $tablename;");
		echo '<div style="background-color: rgb(207, 235, 247);" id="message" class="updated fade"><p><strong>Stats reset.</strong></p></div>';	
	}
} else {
	$ips = file_get_contents(ABSPATH.'wp-content/plugins/'.dirname(plugin_basename(__FILE__))."/exclusions.dat");
}
echo "<div class='wrap'>";
echo "<h2>Simple Stats Options</h2>";
echo "<table><tr><td>";
echo "<form method='post' action='".$_SERVER['REQUEST_URI']."'>";
echo "Your IP: " . $_SERVER['REMOTE_ADDR'] . "<br />";
echo "IPs for exclusion (one on each line):<br />";
echo "<textarea name='ips' style='width: 200px; height: 300px;'>$ips</textarea><br />";
echo "<input type='hidden' name='save' value='1' />";
echo "<input type='submit' value='Update Exclusion List' />";
echo "</form>";
echo "</td><td>";
echo "<form method='post' action='".$_SERVER['REQUEST_URI']."'>";
echo "Click below to reset stats.<br /><strong style='color: red;'>THIS CANNOT BE UNDONE</strong><br />";
echo "<input type='hidden' name='reset' value='1' />";
echo "<input type='submit' value='Reset Stats' />";
echo "</form></td></tr></table>";
echo "</div>";
}

function intstDrilldown($query) {
	$result = mysql_query($query) or die(mysql_error());
	if (mysql_num_rows($result)!=0) {
		echo "<table style='width: 100%;'>";
		echo "<tr style='font-weight: bold;'><td style='border: 1px solid #CCC; '></td>";
			for ($y=0;$y<mysql_num_fields($result);$y++) {
				echo "<td style='border: 1px solid #CCC; '>" . mysql_field_name($result, $y) . "</td>";
			}
		echo "</tr>\n";
	while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
		$rowcount++;
		echo "<tr><td style='border: 1px solid #CCC; '>" . $rowcount . ".</td>";
			for ($y=0;$y<mysql_num_fields($result);$y++) {
				if (mysql_field_name($result, $y)=="IP") {
					echo "<td style='border: 1px solid #CCC; '><a href='".$_SERVER['SCRIPT_NAME']."?page=".strip_tags($_GET['page'])."&freq=".strip_tags($_GET['freq'])."&drilldown=ip&ip=".$row[$y]."'>" . $row[$y] . "</a></td>";
				} else {
					if (substr($row[$y],0,7)=="http://") {
						$parsed_url = parse_url($row[$y]);
						echo "<td style='border: 1px solid #CCC; '><a href='" . $row[$y] . "'>" . $parsed_url['host'] . "</a></td>";
						} else {
						echo "<td style='border: 1px solid #CCC; '>" . $row[$y] . "</td>";
					}
				}
			}
		echo "</tr>\n";
	}
	echo "</table>";
	} 
	mysql_free_result($result); 
}

function intstAdminMenu() {
global $wpdb;
$tablename = $wpdb->prefix . "intellistats";
$limit = 20;
$font = "Tahoma";

switch ($_GET['freq']) {
	case 1:
	$freq = 0;
	break;
	case 2:
	$freq = 7;
	break;
	case 3:
	$freq = 30;
	break;
	default:
	$freq = 7;
	break;
}

if ($_GET['drilldown']) {
	switch (strip_tags($_GET['drilldown'])) {
	case "ip":
		$ip = strip_tags($_GET['ip']);
		echo "<div class='wrap'><h2>IP Drilldown: $ip</h2>";
		echo "Host name: " . gethostbyaddr($ip) . "<br />";

		$record = intstGeolocate($ip);
		echo "Area code: " .  $record->area_code . "<br />";
		echo "Postal code: " . $record->postal_code . "<br />";
		echo "City: " . $record->city . "<br />";
		echo "Country: " . $record->country_name . "<br />";
		echo "Lattitude: " . $record->latitude . "<br />";
		echo "Longitude: " . $record->longitude . "<br />";
		
		$google_maps_link = "http://maps.google.com/maps?f=q&hl=en&geocode=&q=".$record->latitude.",".$record->longitude;
		echo "Locate: <a href='$google_maps_link'>[Google Maps]</a><br />";
		echo "More information: <a href='http://ws.arin.net/whois/?queryinput=" . $ip . "'>[WHOIS]</a>";
		echo "</div>";
		echo "<div class='wrap'>";
		intstDrilldown("SELECT DATE_FORMAT(timestamp,'%m/%d/%Y %h:%i:%s') AS `Visit Time`, Page, Referer, Useragent FROM $tablename WHERE ip='$ip' ORDER BY timestamp DESC;");
		echo "</div>";
		break;
	case "domain":
		$domain = strip_tags($_GET['domain']);
		echo "<div class='wrap'><h2>Domain drilldown: $domain</h2>";
		intstDrilldown("SELECT DATE_FORMAT(timestamp,'%m/%d/%Y %h:%i:%s') AS `Visit Time`, IP, Page, Referer, Useragent FROM $tablename WHERE referer_domain='$domain' AND timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) ORDER BY referer_domain, timestamp DESC;");
		echo "</div>";
		break;
	case "country":
		$country = strip_tags($_GET['country']);
		echo "<div class='wrap'><h2>Country drilldown: $country</h2>";
		intstDrilldown("SELECT DATE_FORMAT(timestamp,'%m/%d/%Y %h:%i:%s') AS `Visit Time`, IP, Page, Referer, Useragent FROM $tablename WHERE country='$country' AND timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) ORDER BY timestamp DESC;");
		echo "</div>";
		break;
	case "hit":
		$hit = strip_tags($_GET['hit']);
		echo "<div class='wrap'><h2>Page drilldown: $hit</h2>";
		intstDrilldown("SELECT DATE_FORMAT(timestamp,'%m/%d/%Y %h:%i:%s') AS `Visit Time`, IP, Referer, Useragent FROM $tablename WHERE page='$hit' AND timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) ORDER BY timestamp DESC;");
		echo "</div>";
		break;
	case "search":
		$search = strip_tags($_GET['search']);
		echo "<div class='wrap'><h2>Search drilldown: $search</h2>";
		intstDrilldown("SELECT DATE_FORMAT(timestamp,'%m/%d/%Y %h:%i:%s') AS `Visit Time`, IP, Page, Referer, Useragent FROM $tablename WHERE search_terms='$search' AND timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) ORDER BY timestamp DESC;");
		echo "</div>";
		break;
	case "day":
		$day = strip_tags($_GET['day']);
		echo "<div class='wrap'><h2>Date drilldown: $search</h2>";
		intstDrilldown("SELECT DATE_FORMAT(timestamp,'%m/%d/%Y %h:%i:%s') AS `Visit Time`, IP, Page, Referer, Useragent FROM $tablename WHERE DATE_FORMAT(timestamp,'%M %d, %Y')='$day' AND timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) ORDER BY timestamp DESC;");
		echo "</div>";
		break;
	}
} else {

//Check to see if the database is populated
//global $freq;
$result = mysql_query("SELECT COUNT(*) AS `Users` FROM $tablename WHERE timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) GROUP BY DATE(`timestamp`) ORDER BY `Users` DESC LIMIT 1;");
$max = mysql_result($result,0);
$result = mysql_query("SELECT COUNT(DISTINCT ip) AS `Users` FROM $tablename WHERE timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) GROUP BY DATE(`timestamp`) ORDER BY `Users` DESC LIMIT 1;");
$max_unique = mysql_result($result,0);
if ($result) {

echo "<div class='wrap'>";

echo "<div style='text-align: right;'><form method='get' action='".$_SERVER['SCRIPT_NAME']."'>";
echo "<input type='hidden' value='".$_GET['page']."' name='page' />";
echo "<select name='freq' onChange='submit();'>";
if ($_GET['freq']==1)  { echo "<option value='1' selected='selected'>Today</option>"; } else { echo "<option value='1'>Today</option>"; }
if ($_GET['freq']==2 OR (!$_GET['freq']))  { echo "<option value='2' selected='selected'>This Week</option>"; } else { echo "<option value='2'>This Week</option>"; }
if ($_GET['freq']==3)  { echo "<option value='3' selected='selected'>This Month</option>"; } else { echo "<option value='3'>This Month</option>"; }
echo "</select></form></div>";

echo "<h2>Traffic Overview</h2>";

//widget variables
$width = 380;
$height = 250;

//Graph of visits/visitors this week
$total_unique = 0;
$total_visits = 0;
for ($x=-$freq;$x<=0;$x++) {
$result = mysql_query("SELECT DATE_FORMAT(timestamp, '%m/%d'), COUNT(DISTINCT ip), COUNT(*)-COUNT(DISTINCT ip) FROM $tablename WHERE DATE(timestamp)=DATE_ADD(CURDATE(), INTERVAL $x DAY) GROUP BY DATE_FORMAT(timestamp, '%m/%d') LIMIT 1;");
$row = mysql_fetch_array($result);
$labels_array[$x+7] = $row[0];
$visitors_array[$x+7] = round((($row[1]/$max)*100), 1);
$unique_array[$x+7] = round((($row[1]/$max_unique)*100), 1);
$total_unique += $row[1];
$hits_array[$x+7] = round((($row[2]/$max)*100), 1);
$total_visits += ($row[2]+$row[1]);
}
$labels = implode("|",$labels_array);
$visitors = implode(",",$visitors_array);
$unique = implode(",",$unique_array);
$hits = implode(",",$hits_array);
$bar_width = number_format(280/($freq+1));

echo "<div style='float: right; background: #EEE; font-size: 16pt; padding: 20px;'>Unique Visits: $total_unique<br />Total Page Hits: $total_visits</div>";

if ($freq==7) {
$chart_api = "http://chart.apis.google.com/chart?cht=bvs&chd=t:$visitors|$hits&chs=".$width."x".$height."&chl=$labels&chco=006699,0099FF&chxt=y&chxr=0,0,$max&chbh=$bar_width&chtt=Hits%20vs.%20Unique%20Visitors";
echo "<img src='$chart_api' />";
$chart_api = "http://chart.apis.google.com/chart?cht=bvs&chd=t:$unique&chs=".$width."x".$height."&chl=$labels&chco=006699,0099FF&chxt=y&chxr=0,0,$max_unique&chbh=$bar_width&chtt=Unique%20Visitors";
echo "<img src='$chart_api' />";
}

if ($freq==0) {
$chart_api = "http://chart.apis.google.com/chart?cht=p&chd=t:$visitors,$hits&chs=380x250&chco=006699,0099FF&chl=Unique%20Hits%20|Hits%20&chtt=Hits%20vs.%20Unique%20Hits";
echo "<img src='$chart_api' />";
}

//Graph of traffic sources
$result = mysql_query("SELECT (SUM(IF(referer='', 1, 0))/COUNT(*))*100 AS `Direct`, (SUM(IF(search_terms!='', 1, 0))/COUNT(*))*100 AS `Search`, (SUM(IF(search_terms='' AND referer!='', 1,0))/COUNT(*))*100 AS `Other` FROM $tablename WHERE DATE(timestamp)>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY);");// or die("SELECT (SUM(IF(referer='', 1, 0))/COUNT(*))*100 AS `Direct`, (SUM(IF(search_terms!='', 1, 0))/COUNT(*))*100 AS `Search`, (SUM(IF(search_terms='' AND referer!='', 1,0))/COUNT(*))*100 AS `Other` FROM $tablename WHERE DATE(timestamp)>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY);" . mysql_error());
$row = mysql_fetch_array($result);
$direct = round($row[0]);
$search = round($row[1]);
$other = round($row[2]);
$chart_api = "http://chart.apis.google.com/chart?cht=p&chd=t:$direct,$search,$other&chs=380x250&chl=Direct%20($direct%)|Search%20($search%)|Other%20($other%)&chtt=Traffic%20Sources";
echo "<img src='$chart_api' />";
echo "<div style='clear: both;'></div></div>";

//View aggregate information
echo "<div class='wrap'>";
echo "<table style='width: 100%;'><tr><td style='vertical-align: top;'>";

//Top Visitors
echo "<h2>Top Visitors</h2>";
$result = mysql_query("SELECT ip, count(*) AS `Count` FROM $tablename WHERE timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) GROUP BY ip ORDER BY `Count` DESC LIMIT $limit;");
	if (mysql_num_rows($result)>0) {
		echo "<ol style='font: 9pt $font;'>";
			while($row = mysql_fetch_array($result)) {
				echo "<li><a href='".$_SERVER['SCRIPT_NAME']."?page=".strip_tags($_GET['page'])."&freq=".strip_tags($_GET['freq'])."&drilldown=ip&ip=".$row[0]."'>" . gethostbyaddr($row[0]) . "</a> (" . $row[1] . ") <a href='http://ws.arin.net/whois/?queryinput=" . $row[0] . "'>[WHOIS]</a></li>";
			}
		echo "</ol>";
	} else {
		echo "Resultset empty";
	}
echo "</td><td style='vertical-align: top;'>";

//Top Pages
echo "<h2>Top Pages</h2>";
$result = mysql_query("SELECT page, count(*) AS `Count` FROM $tablename WHERE timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) GROUP BY page ORDER BY `Count` DESC LIMIT $limit;");
	if (mysql_num_rows($result)>0) {
		echo "<ol style='font: 9pt $font;'>";
			while($row = mysql_fetch_array($result)) {
				if ($row[0]=="/") { $page = "Home page"; } else { $page = $row[0]; }
				echo "<li><a href='".$_SERVER['SCRIPT_NAME']."?page=".strip_tags($_GET['page'])."&freq=".strip_tags($_GET['freq'])."&drilldown=hit&hit=".$row[0]."'>" . $page . "</a> (" . $row[1] . ") <a href='" . $row[0] . "'>[See Page]</a></li>";
			}
		echo "</ol>";
	} else {
		echo "Resultset empty";
	}
echo "</td></tr>";

//Top Referers
echo "<tr><td style='vertical-align: top;'>";
echo "<h2>Top Referers</h2>";
$result = mysql_query("SELECT referer_domain , count(*) AS `Count` FROM $tablename WHERE timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) AND referer IS NOT NULL GROUP BY referer_domain ORDER BY `Count` DESC LIMIT $limit;");
	if (mysql_num_rows($result)>0) {
		echo "<ol style='font: 9pt $font;'>";
			while($row = mysql_fetch_array($result)) {
				if ($row[0]!="") {
					echo "<li><a href='".$_SERVER['SCRIPT_NAME']."?page=".strip_tags($_GET['page'])."&freq=".strip_tags($_GET['freq'])."&drilldown=domain&domain=".$row[0]."'>" . $row[0] . "</a> (" . $row[1] . ") <a href='http://".$row[0]."'>[See Domain]</a></li>"; 
				} else {
				echo "<li>Direct Hit (" . $row[1] . ")</li>";
				}
			}
		echo "</ol>";
	} else {
		echo "Resultset empty";
	}

//Top Search Terms
echo "</td><td style='vertical-align: top;'>";
echo "<h2>Top Search Terms</h2>";
$result = mysql_query("SELECT search_terms, count(*) AS `Count`, referer FROM $tablename WHERE timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) AND referer IS NOT NULL GROUP BY search_terms ORDER BY `Count` DESC LIMIT $limit;");
	if (mysql_num_rows($result)>0) {
		echo "<ol style='font: 9pt $font;'>";
			while($row = mysql_fetch_array($result)) {
				if ($row[0]) { echo "<li><a href='".$_SERVER['SCRIPT_NAME']."?page=".strip_tags($_GET['page'])."&freq=".strip_tags($_GET['freq'])."&drilldown=search&search=".$row[0]."'>" . $row[0] . "</a> (" . $row[1] . ") <a href='".$row[2]."'>[See results]</a></li>"; }
			}
		echo "</ol>";
	} else {
		echo "Resultset empty";
	}
echo "</td></tr>";

//Top Days
echo "<tr><td style='vertical-align: top;'>";
echo "<h2>Top Days in the Past Month</h2>";
$result = mysql_query("SELECT DATE_FORMAT(timestamp, '%m'),DATE_FORMAT(timestamp, '%d'),DATE_FORMAT(timestamp, '%Y'), count(DISTINCT ip) AS `Count` FROM $tablename WHERE timestamp>=DATE_ADD(CURDATE(), INTERVAL -1 MONTH) GROUP BY DATE_FORMAT(timestamp, '%M %d, %Y') ORDER BY `Count` DESC LIMIT $limit;");
	if (mysql_num_rows($result)>0) {
		echo "<ol style='font: 9pt $font;'>";
			while($row = mysql_fetch_array($result)) {
				$link = get_bloginfo('url') . "/archives/" . $row[2] . "/" . $row[0] . "/" . $row[1] . "/";
				/* Check to see if page exists before putting link (Took too long, so function was removed)
				$status = get_http_headers($link);
				if ($status=200) { $link_append = " <a href='$link'>[See Posts]</a></li>"; } else { $link_append = ""; } */
				$link_append = " <a href='$link'>[See Posts]</a></li>";
				$date = date("F d, Y",mktime(0,0,0,$row[0], $row[1], $row[2]));
				echo "<li><a href='".$_SERVER['SCRIPT_NAME']."?page=".strip_tags($_GET['page'])."&freq=".strip_tags($_GET['freq'])."&drilldown=day&day=".$date."'>" . $date . "</a> (" . $row[3] . ")$link_append</l1>";
			}
		echo "</ol>";
	} else {
		echo "Resultset empty";
	}

//Top Countries
echo "</td><td style='vertical-align: top;'>";
echo "<h2>Top Countries</h2>";
$result = mysql_query("SELECT country, count(DISTINCT ip) AS `Count` FROM $tablename WHERE timestamp>=DATE_ADD(CURDATE(), INTERVAL -$freq DAY) GROUP BY country ORDER BY `Count` DESC LIMIT $limit;");
	if (mysql_num_rows($result)>0) {
		echo "<ol style='font: 9pt $font;'>";
			while($row = mysql_fetch_array($result)) {
				$google_maps_link = "http://maps.google.com/maps?f=q&hl=en&geocode=&q=" . str_replace(" ","+",$row[0]) .  "&ie=UTF8";
				if ($row[0]) { echo "<li><a href='".$_SERVER['SCRIPT_NAME']."?page=".strip_tags($_GET['page'])."&freq=".strip_tags($_GET['freq'])."&drilldown=country&country=".$row[0]."'>" . $row[0] . "</a> (" . $row[1] . ") <a href='$google_maps_link'>[Google Maps]</a></li>"; }
			}
		echo "</ol>";
	} else {
		echo "Resultset empty";
	}
echo "</td></tr>";

echo "</table></div>";

} else {
echo "<div class='wrap'>There have been no hits to your web page since this plugin was activated.</div>";
} //End of records check in DB
} //End of POST check
} //End of Admin Page

function intstAddMenus() {
add_menu_page('IntelliStats', 'IntelliStats', 10, __FILE__, 'intstAdminMenu');
add_submenu_page('options-general.php', 'IntelliStats Options', 'IntelliStats', 10, __FILE__, 'intstOptionsMenu');
}

add_action('wp_footer', 'intstAppend');
add_action('admin_menu', 'intstAddMenus');
register_activation_hook(__FILE__,'intstActivate');
?>