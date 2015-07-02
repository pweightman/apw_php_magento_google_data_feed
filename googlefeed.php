<?php
require_once '/home/public_html/app/Mage.php';

Mage::app();
Mage::app()->setCurrentStore(1);
try{
	$nsUrl = 'http://base.google.com/ns/1.0';
	$doc = new DOMDocument('1.0', 'UTF-8');
	
	$rootNode = $doc->appendChild($doc->createElement('rss'));
    $rootNode->setAttribute('version', '2.0');
    $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:g', $nsUrl);

    $channelNode = $rootNode->appendChild($doc->createElement('channel'));
    $channelNode->appendChild($doc->createElement('title', 'APW Trading Google Data Feed'));
    $channelNode->appendChild($doc->createElement('description', 'Google Products Data Feed'));
    $channelNode->appendChild($doc->createElement('link', 'http://www.apwtrading.co.uk.co.uk'));

	$products = Mage::getModel('catalog/product')->getCollection()
    	->addAttributeToSelect('id')
    	->addAttributeToFilter('status', 1)
    	->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
    	->getAllIds();

    $counter_test = 0;
    foreach($products as $pid) {
		$_product = Mage::getModel('catalog/product')->load($pid);  
		
		if ($_product->getAttributeText('googleblock') == 'No') {
			switch ($_product->getTypeId()) {
				case 'simple':
					process_simple_product($_product, $channelNode, $doc);
					break;
				case 'configurable':
					process_configurable_product($_product, $channelNode, $doc);
					break;
			}
			++$counter_test;
		}
        
    }

	
	echo $counter_test . " Products Processed.";
	$doc->save('GoogleShopping.xml');

	}
	catch(Exception $e){
    	die($e->getMessage());
}

function do_isinstock($_product) {
	
	$_stockItem = $_product->getStockItem();
	if($_stockItem->getIsInStock())
	{
    	$stockval = 'in stock';
	}
	else
	{
    	$stockval = 'out of stock';
	}
	return $stockval;
}

function do_isadult($_product) {

	switch ($_product->getAttributeText('familysafe')) {
		case 'No':
			$isadult = "FALSE";
		default:
			$isadult = "TRUE";
	}
	return($isadult);
}


function do_producttypes($_product, &$doc, &$itemNode) {
	$catCollection = $_product->getCategoryIds();
	
	$firstcat = "";
	foreach ($catCollection as $catid) {
		$category = Mage::getModel('catalog/category')->load($catid);
		
		$fullcatname = "";
		
		$path = $category->getPath();
		$ids = explode('/', $path);
		unset($ids[0]);
		$level = 0;
		foreach  ($ids as $cid) {
			if ($level >= 1) {
				$xcat = Mage::getModel('catalog/category')->load($cid);
				if (strlen($fullcatname) > 0) {
					$fullcatname .= " -> " . $xcat->getName();
				} else {
					$fullcatname .= $xcat->getName();
				}
				
			}
			++$level;
		}	
		if (strlen($firstcat) == 0) {
			$firstcat = $fullcatname;
		}
		$itemNode->appendChild($doc->createElement('g:product_type'))->appendChild($doc->createTextNode($fullcatname));  		
	}
	return $firstcat;
}

function get_googleTaxonmyCat($intcat) {

	$resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
	
	$google_cat = $readConnection->fetchOne("SELECT google_cat FROM lll_google_taxonomy WHERE local_catname = '" . $intcat . "'");
	
	if (!$google_cat) {
		echo "Missing Google Category for : " . $intcat . "</br>"; 
		$writeConnection = $resource->getConnection('core_write');
		$query = "INSERT INTO lll_google_taxonomy (local_catname) VALUES ('" . $intcat. "')";
		try {
			$writeConnection->query($query);
			} catch (exception $e) {
			}
	}
	
	return($google_cat);
}

function process_simple_product($_product, &$channelNode, &$doc) {

	$itemNode = $channelNode->appendChild($doc->createElement('item'));
	
	$itemNode->appendChild($doc->createElement('g:id'))->appendChild($doc->createTextNode($_product->getId()));
	$itemNode->appendChild($doc->createElement('g:title'))->appendChild($doc->createTextNode($_product->getName()));
	$itemNode->appendChild($doc->createElement('g:description'))->appendChild($doc->createTextNode($_product->getShortDescription()));
	$itemNode->appendChild($doc->createElement('g:image_link'))->appendChild($doc->createTextNode($_product->getImageUrl()));
	$itemNode->appendChild($doc->createElement('g:brand'))->appendChild($doc->createTextNode($_product->getAttributeText('brand')));
	$itemNode->appendChild($doc->createElement('g:link'))->appendChild($doc->createTextNode($_product->getProductUrl()));
	$itemNode->appendChild($doc->createElement('g:gender'))->appendChild($doc->createTextNode($_product->getAttributeText('gender')));
	$itemNode->appendChild($doc->createElement('g:material'))->appendChild($doc->createTextNode($_product->getAttributeText('mainmaterial')));
	$itemNode->appendChild($doc->createElement('g:pattern'))->appendChild($doc->createTextNode($_product->getAttributeText('pattern')));
	
	$topcat = do_producttypes($_product, $doc, $itemNode);
	$itemNode->appendChild($doc->createElement('g:google_product_category'))->appendChild($doc->createTextNode(get_googleTaxonmyCat($topcat)));
	
	$itemNode->appendChild($doc->createElement('g:mpn'))->appendChild($doc->createTextNode($_product->getSku()));
	if (strlen($_product->getEan()) > 7) {
		$itemNode->appendChild($doc->createElement('g:gtin'))->appendChild($doc->createTextNode($_product->getEan()));
	}
	
	$itemNode->appendChild($doc->createElement('g:price'))->appendChild($doc->createTextNode($_product->getPrice()));
	$itemNode->appendChild($doc->createElement('g:sale_price'))->appendChild($doc->createTextNode($_product->getSpecialPrice()));
	
	$itemNode->appendChild($doc->createElement('g:size'))->appendChild($doc->createTextNode($_product->getAttributeText('size')));
	$itemNode->appendChild($doc->createElement('g:color'))->appendChild($doc->createTextNode($_product->getAttributeText('color')));
	
	$itemNode->appendChild($doc->createElement('g:availability'))->appendChild($doc->createTextNode(do_isinstock($_product)));
	
	$itemNode->appendChild($doc->createElement('g:size_system'))->appendChild($doc->createTextNode('uk'));
	$itemNode->appendChild($doc->createElement('g:age_group'))->appendChild($doc->createTextNode('adult'));
	$itemNode->appendChild($doc->createElement('g:condition'))->appendChild($doc->createTextNode('new'));
	$itemNode->appendChild($doc->createElement('g:identifier_exists'))->appendChild($doc->createTextNode('TRUE'));
	$itemNode->appendChild($doc->createElement('g:adult'))->appendChild($doc->createTextNode(do_isadult($_product)));
	
}

function process_configurable_product($_product, &$channelNode, &$doc) {

	$_childProducts = Mage::getModel('catalog/product_type_configurable')
                    	->getUsedProducts(null,$_product);
	
	foreach($_childProducts as $_child) {   
		$itemNode = $channelNode->appendChild($doc->createElement('item'));
		
		$_childproduct = Mage::getModel('catalog/product')->load($_child->getID());
	
		$itemNode->appendChild($doc->createElement('g:id'))->appendChild($doc->createTextNode($_childproduct->getId()));
		$itemNode->appendChild($doc->createElement('g:item_group_id'))->appendChild($doc->createTextNode($_product->getSku()));
		$itemNode->appendChild($doc->createElement('g:title'))->appendChild($doc->createTextNode($_product->getName()));
		$itemNode->appendChild($doc->createElement('g:description'))->appendChild($doc->createTextNode($_product->getShortDescription()));
		$itemNode->appendChild($doc->createElement('g:image_link'))->appendChild($doc->createTextNode($_product->getImageUrl()));
		$itemNode->appendChild($doc->createElement('g:brand'))->appendChild($doc->createTextNode($_product->getAttributeText('brand')));
		$itemNode->appendChild($doc->createElement('g:link'))->appendChild($doc->createTextNode($_product->getProductUrl()));
		$itemNode->appendChild($doc->createElement('g:gender'))->appendChild($doc->createTextNode($_product->getAttributeText('gender')));
		$itemNode->appendChild($doc->createElement('g:material'))->appendChild($doc->createTextNode($_product->getAttributeText('mainmaterial')));
		$itemNode->appendChild($doc->createElement('g:pattern'))->appendChild($doc->createTextNode($_product->getAttributeText('pattern')));

		$topcat = do_producttypes($_product, $doc, $itemNode);
		$itemNode->appendChild($doc->createElement('g:google_product_category'))->appendChild($doc->createTextNode( get_googleTaxonmyCat($topcat)));
		
		$itemNode->appendChild($doc->createElement('g:mpn'))->appendChild($doc->createTextNode($_childproduct->getSku()));
		if (strlen($_product->getEan()) > 7) {
			$itemNode->appendChild($doc->createElement('g:gtin'))->appendChild($doc->createTextNode($_childproduct->getEan()));
		}
	
		$itemNode->appendChild($doc->createElement('g:price'))->appendChild($doc->createTextNode($_childproduct->getPrice()));
		$itemNode->appendChild($doc->createElement('g:sale_price'))->appendChild($doc->createTextNode($_childproduct->getSpecialPrice()));

		$itemNode->appendChild($doc->createElement('g:size'))->appendChild($doc->createTextNode($_childproduct->getAttributeText('size')));
		$itemNode->appendChild($doc->createElement('g:color'))->appendChild($doc->createTextNode($_childproduct->getAttributeText('color')));

		$itemNode->appendChild($doc->createElement('g:availability'))->appendChild($doc->createTextNode(do_isinstock($_childproduct)));
		
		$itemNode->appendChild($doc->createElement('g:size_system'))->appendChild($doc->createTextNode('uk'));
		$itemNode->appendChild($doc->createElement('g:age_group'))->appendChild($doc->createTextNode('adult'));
		$itemNode->appendChild($doc->createElement('g:condition'))->appendChild($doc->createTextNode('new'));
		$itemNode->appendChild($doc->createElement('g:identifier_exists'))->appendChild($doc->createTextNode('TRUE'));
		$itemNode->appendChild($doc->createElement('g:adult'))->appendChild($doc->createTextNode(do_isadult($_product)));			

	}
}

?>
