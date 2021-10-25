<?php

use WHMCS\View\Menu\Item as MenuItem;



add_hook('ClientAreaPrimaryNavbar', 1, function (MenuItem $primaryNavbar)
{
	$client = Menu::context('client');
	if (!$client) { // not logged in
            $primaryNavbar->removeChild('Affiliates');
            if($primaryNavbar->getChild('Knowledgebase')){
                $primaryNavbar->getChild('Knowledgebase')->moveUp();
	        }   
	}
	
});




