<?php
namespace TYPO3\PackageCatalog\Controller;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.PackageCatalog".  *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Standard controller for the TYPO3.PackageCatalog package 
 *
 * @Flow\Scope("singleton")
 */
class StandardController extends \TYPO3\Flow\Mvc\Controller\ActionController {

	/**
	 * Index action
	 *
	 * @return void
	 */
	public function indexAction() {
		$packagesFile = FLOW_PATH_DATA . 'Packages/packages-typo3-flow.json';
		$packages = json_decode(file_get_contents($packagesFile));
		#var_dump($packages);
		$this->view->assign('packages', get_object_vars($packages));
	}

}

?>