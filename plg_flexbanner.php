<?php
/**
 * @version $Id: plg_flexbanner.php,v 1.2 2010/04/29 17:44:09 carlos.delfino Exp $
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @Author: Carlos Delfino joomla@full.srv.br, based on work of keep - http://joomla.blog.hu - based on: Tobbworld.de - http://www.tobbworld.de
 *
 *
 **/

/** Ensure this file is being included by a parent file */
defined( '_JEXEC' ) or die( 'Restricted access' );

$mainframe->registerEvent( 'onPrepareContent', 'newBanner' );

jimport( 'joomla.plugin.plugin' );

/**
 * este plugin existe apenas a titulo de inserção de funções para uso
 * nos modulos, componentes, outro plugins e templates dos sistemas
 * desenvolvidos pelo CSAT.
 *
 * @author Delfino
 *
 */
class plgContentFlexbanner extends JPlugin {

	private $lastmosaddbanner_params = array();

	function __construct($subject, $config){
		parent::__construct($subject, $config);
	}

	function onPrepareContent( &$article, &$params, $limitstart) {

		$database =& JFactory::getDBO();
		if ( JString::strpos( $article->text, 'flexbanner' ) === false ) {
			return true;
		}

		$plugin =& JPluginHelper::getPlugin('content', 'plg_flexbanner');

		if ( !JString::strpos( $article->text, "{flexbanner}") ) {
			if (preg_match('{flexbanner:id=\d+(,\d+)*}', $article->text)) {
				$article->text = $this->processWithId($article );
			}
			if (preg_match('{flexbanner:clientid=\d+(,\d+)*}', $article->text)) {
				$article->text = $this->processWithClientId($article);
			}
			if (preg_match('{flexbanner:location=\d+(,\d+)*}', $article->text)) {
				$article->text = $this->processWithCatId($article);
			}
			if (preg_match('{flexbanner:cat=\d+(,\d+)*}', $article->text)) {
				$text = $article->text;
				if (preg_match_all('/{flexbanner:location=\d+(,\d+)*}/', $text, $mosaddbanner_matches, PREG_PATTERN_ORDER) > 0) {

					foreach ($mosaddbanner_matches[0] as $mosaddbanner_match) {
						$mosaddbanner_output = "";
						$mosaddbanner_match = str_replace("{flexbanner:location=", "", $mosaddbanner_match);
						$mosaddbanner_match = str_replace("}", "", $mosaddbanner_match);
						$mosaddbanner_params = array();
						$mosaddbanner_params = explode(",", $mosaddbanner_match);
						$database->setQuery(
					   "SELECT * FROM #__fabanner AS b
					    JOIN #__fabannerlocation as l ON b.bannerid = l.bannerid   
					    WHERE l.locationid IN($mosaddbanner_match) AND b.published=1 
					    ORDER BY rand() LIMIT 1" );

						$numrows = $database->loadResult();

						if($numrows >0 ) {
							$banner = $database->loadObject( );
							$text = preg_replace("/{flexbanner:location=\d+(,\d+)*}/", viewBannerWithId($banner->bannerid), $text, 1);
							$article->text = str_replace ("{flexbanner}", "", $article->text);
						} else {
							$text = preg_replace ("/{flexbanner:location=\d+(,\d+)*}/", "", $text);
						}
					}
					$article->text = $text;

				}
			}

			return true;
		} else {
			$article->text = str_replace ("{flexbanner}", $this->viewbanner2(), $article->text);
		}

	}


	function defaultBanner(){
		return "<!-- no Banner -->";
	}
	function viewBannerWithId( $bannerId ) {

		$database =& JFactory::getDBO();

		$database->setQuery( "SELECT * FROM #__fabanner WHERE bannerid=$bannerId AND published=1" );
		$numrows = $database->loadResult();
		if($numrows == 0 ) {
			return defaultBanner();
		}

		$banner = null;
		if ($banner = $database->loadObject()) {

			if ($numrows > 0) {
				// Check if this impression is the last one and print the banner
				if (($banner->maximpressions>0) && ($banner->maximpressions <= $banner->impressions)) {
					return defaultBanner();
				}
				$banner->impmade++;
				$database->setQuery( "UPDATE #__fabanner SET impressions=impressions+1 WHERE bannerid='$banner->bannerid'" );
				if(!$database->query()) {
					echo $database->stderr( true );
					return;
				}

				if (trim( $banner->custombannercode )) {
					return $banner->custombannercode;
				} else if (eregi( "(\.bmp|\.gif|\.jpg|\.jpeg|\.png)$", $banner->imageurl )) {
					$imageurl = JURI::base().JRoute::_('images/banners/'.$banner->imageurl);
					$banner1 = "<a href=\"index.php?option=com_flexbanner&amp;task=click&amp;bannerid=$banner->bannerid\" target=\"_blank\"><img src=\"$imageurl\" border=\"0\" /></a>";
					return $banner1;
				} else if (eregi("\.swf$", $banner->imageurl)) {
					$imageurl = JURI::base().JRoute::_('images/banners/'.$banner->imageurl);
					$banner2= "<object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://fpdownload.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0\" border=\"5\">
							<param name=\"movie\" value=\"$imageurl\"><embed src=\"$imageurl\" loop=\"false\" pluginspage=\"http://www.macromedia.com/go/get/flashplayer\" type=\"application/x-shockwave-flash\"></embed></object>";
					return $banner2;
				}
			}
		} else {
			return defaultBanner();
		}
	}

	private function viewbanner2() {
		$database =& JFactory::getDBO();

		// é muito pessado fazer o count toda vez
		// que ele encontrar um diretiva para exibir
		// o banner, no nosso caso um artigo poderá
		// causar a execução deste count mais de 30
		// vezes por exibição
		$database->setQuery( "SELECT count(*) AS numrows FROM #__fabanner WHERE published=1 AND ((imptotal>impmade) OR (imptotal=0))" );

		$numrows = $database->loadResult();
		if ($numrows == 0) {
			return "";
		}

		$plugin =& JPluginHelper::getPlugin('content', 'plg_flexbanner');
		$loop = $plugin->params->loop;

		$database->setQuery( "SELECT * FROM #__fabanner WHERE published=1 AND ((imptotal>impmade) OR (imptotal=0)) ORDER BY rand() LIMIT 1" );

		$banner = null;
		if ($banner = $database->loadObject( )) {
			if ($numrows > 0) {
				// Check if this impression is the last one and print the banner
				$database->setQuery( "UPDATE #__fabanner SET impmade=impmade+1 WHERE bannerid='$banner->bannerid'" );
				$banner->impmade++;
				if (trim( $banner->custombannercode )) {
					return $banner->custombannercode;
				} else if (eregi( "(\.bmp|\.gif|\.jpg|\.jpeg|\.png)$", $banner->imageurl )) {
					$imageurl = "/images/banners/$banner->imageurl";
					$banner1 = "<a href=\"index.php?option=com_flexbanners&amp;task=click&amp;bannerid=$banner->bannerid\" target=\"_blank\"><img src=\"$imageurl\" border=\"0\"  /></a>";
					return $banner1;
				} else if (eregi("\.swf$", $banner->imageurl)) {
					$imageurl = "/images/banners/".$banner->imageurl;
					$banner2= "<object classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" codebase=\"http://fpdownload.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0\" border=\"5\">
							<param name=\"movie\" value=\"$imageurl\"><embed src=\"$imageurl\" loop=\"$loop\" pluginspage=\"http://www.macromedia.com/go/get/flashplayer\" type=\"application/x-shockwave-flash\"></embed></object>";
					return $banner2;
				}
			}
		} else {
			return "";
		}
	}

	// funões de processamento e substituição
	private function processWithLocationId($article) {
		$text = $article->text;
		if (preg_match_all('/{flexbanner:location=\d+(,\d+)*}/', $text, $mosaddbanner_matches, PREG_PATTERN_ORDER) > 0) {
			foreach ($mosaddbanner_matches[0] as $mosaddbanner_match) {
				$mosaddbanner_output = "";
				$mosaddbanner_match = str_replace("{flexbanner:location=", "", $mosaddbanner_match);
				$mosaddbanner_match = str_replace("}", "", $mosaddbanner_match);
				$mosaddbanner_params = array();
				$mosaddbanner_params = explode(",", $mosaddbanner_match);
				$database->setQuery(
					   "SELECT * FROM #__fabanner AS b
					    JOIN #__fabannerlocation as l ON b.bannerid = l.bannerid   
					    WHERE l.locationid IN($mosaddbanner_match) AND b.published=1 
					    ORDER BY rand() LIMIT 1" );

				$numrows = $database->loadResult();

				if($numrows >0 ) {
					$banner = $database->loadObject( );
					$text = preg_replace("/{flexbanner:location=\d+(,\d+)*}/", viewBannerWithId($banner->bannerid), $text, 1);
					$article->text = str_replace ("{flexbanner}", "", $article->text);
				} else {
					$text = preg_replace ("/{flexbanner:location=\d+(,\d+)*}/", "", $text);
				}
			}
			$article->text = $text;

		}
		return $text;
	}
	private function processWithClientId($article) {
		$text = $article->text;
		if (preg_match_all('/{flexbanner:clientid=\d+(,\d+)*}/', $text, $mosaddbanner_matches, PREG_PATTERN_ORDER) > 0) {

			foreach ($mosaddbanner_matches[0] as $mosaddbanner_match) {
				$mosaddbanner_output = "";
				$mosaddbanner_match = str_replace("{flexbanner:clientid=", "", $mosaddbanner_match);
				$mosaddbanner_match = str_replace("}", "", $mosaddbanner_match);
				$mosaddbanner_params = array();
				$mosaddbanner_params = explode(",", $mosaddbanner_match);
				$database->setQuery( "SELECT * FROM #__fabanner WHERE clientid IN($mosaddbanner_match) AND published=1 ORDER BY rand() LIMIT 1" );

				$numrows = $database->loadResult();

				if($numrows >0 ) {
					$banner = $database->loadObject( );
					$text = preg_replace("/{flexbanner:clientid=\d+(,\d+)*}/", viewBannerWithId($banner->bannerid), $text, 1);
					$article->text = str_replace ("{flexbanner}", "", $article->text);
				} else {
					$text = preg_replace ("/{flexbanner:clientid=\d+(,\d+)*}/", "", $text);
				}
			}
			$article->text = $text;

		}
		return $text;
	}private function processWithId(&$article){
		$text = $article->text;
			
		if (preg_match_all('/{flexbanner:id=\d+(,\d+)*}/', $text, $mosaddbanner_matches, PREG_PATTERN_ORDER) > 0) {

			foreach ($mosaddbanner_matches[0] as $mosaddbanner_match) {
				$mosaddbanner_match = str_replace("{flexbanner:id=", "", $mosaddbanner_match);
				$mosaddbanner_match = str_replace("}", "", $mosaddbanner_match);
				$mosaddbanner_params = array();
				$mosaddbanner_params = explode(",", $mosaddbanner_match);

				$mosaddbanner_count = count($mosaddbanner_params);
				if($mosaddbanner_count > 1){
					//pega um novo banner ainda nÃ£usado
					// 1
					for ($i = 0,$used = true;$i<$mosaddbanner_count && $used && $mosadbanner_count <= count($this->lastmosaddbanner_params); $i++ ){
						$this->last = $mosaddbanner_params[array_rand($mosaddbanner_params)]; // 3
						$used = in_array($last,$this->lastmosaddbanner_params); // 4
						if($i+1<$mosaddbanner_count && $used && $mosadbanner_count <= count($this->lastmosaddbanner_params)){
							$this->lastmosaddbanner_params = array();
						}
					}
					if($i == $mosaddbanner_count){
						continue;
					}else if($used){
						continue;
					}
				}else if($mosaddbanner_count == 1 && !in_array($last,$this->lastmosaddbanner_params)){
					$last = $mosaddbanner_params[0];
				}else{
					continue;
				}

				$this->lastmosaddbanner_params[] = $last; // 5

				$bannerHtml = viewBannerWithId($last);

				$text = preg_replace("/{flexbanner:id=\d+(,\d+)*}/", $bannerHtml, $text, 1);
			}

			$text = preg_replace ("/{flexbanner(.+)*}/", "", $text);
			return $text;
		}
	}
}
?>