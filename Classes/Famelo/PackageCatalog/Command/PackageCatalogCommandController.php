<?php
namespace Famelo\PackageCatalog\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.Gerrit".          *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * update command controller for the TYPO3.Gerrit package
 *
 * @Flow\Scope("singleton")
 */
class PackageCatalogCommandController extends \TYPO3\Flow\Cli\CommandController {

	protected $repositories = array(
		'https://packagist.org/' => 'https://packagist.org/packages.json',
		'https://packagist.org/' => 'https://packagist.org/p/providers-latest.json',
		'http://ci.typo3.robertlemke.net/job/composer-packages/ws/repository/' => 'http://ci.typo3.robertlemke.net/job/composer-packages/ws/repository/packages.json'
	);

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Http\Client\Browser
	 */
	protected $browser;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Http\Client\CurlEngine
	 */
	protected $browserRequestEngine;

	/**
     * @var \TYPO3\Flow\Configuration\ConfigurationManager
     * @Flow\Inject
     */
    protected $configurationManager;

	/**
	 * This command fetches the new versions of the packages.json from the known repositories
	 *
	 * This command fetches the new versions of the packages.json from the known repositories
	 *
	 * @return void
	 */
	public function updateCommand() {
		$this->browser->setRequestEngine($this->browserRequestEngine);
		$flowPackages = array();

		foreach ($this->repositories as $baseUrl => $repository) {
			$domain = parse_url($repository, PHP_URL_HOST);
			#$baseUrl = dirname($repository) . '/';
			$basePath = FLOW_PATH_DATA . 'Packages/' . $domain;

			if (!is_dir(FLOW_PATH_DATA . 'Packages/')) {
				mkdir(FLOW_PATH_DATA . 'Packages/');
			}

			if (!is_dir(FLOW_PATH_DATA . 'Packages/' . $domain)) {
				mkdir(FLOW_PATH_DATA . 'Packages/' . $domain);
			}

			$response = $this->browser->request($repository);
			$packagesFile = $basePath . '/packages.json';
			file_put_contents($packagesFile, $response->getContent());
			$packageList = json_decode($response->getContent());

			if (isset($packageList->includes)) {
				$includes = get_object_vars($packageList->includes);
				if (!empty($includes)) {
					foreach ($includes as $file => $meta) {
						$packagesFile = $basePath . '/' . $file;
						if (file_exists($packagesFile) && sha1_file($packagesFile) == $meta->sha1) {
							continue;
						}
						if (!is_dir(dirname(($packagesFile)))) {
							\TYPO3\Flow\Utility\Files::createDirectoryRecursively(dirname($packagesFile));
						}
						try {
							$response = $this->browser->request($baseUrl . $file);
							file_put_contents($packagesFile, $response->getContent());
						} catch(\Exception $e) {
						}
					}
				}
			}

			if (isset($packageList->providers)) {
				$providers = get_object_vars($packageList->providers);
				if (!empty($providers)) {
					foreach ($providers as $file => $meta) {
						$packagesFile = $basePath . '/' . $file;
						if (file_exists($packagesFile) && sha1_file($packagesFile) == $meta->sha1) {
							continue;
						}
						if (!is_dir(dirname(($packagesFile)))) {
							\TYPO3\Flow\Utility\Files::createDirectoryRecursively(dirname($packagesFile));
						}
						try {
							$response = $this->browser->request($baseUrl . $file);
							file_put_contents($packagesFile, $response->getContent());
						} catch(\Exception $e) {
						}
					}
				}
			}

			$files = \TYPO3\Flow\Utility\Files::readDirectoryRecursively($basePath, '.json');
			foreach ($files as $file) {
				$packagesObject = json_decode(file_get_contents($file));
				$flowPackages = array_merge($flowPackages, $this->filterFlowPackages($packagesObject->packages));
			}
		}
		$flowPackagesFile = FLOW_PATH_DATA . 'Packages/packages-typo3-flow.json';
		file_put_contents($flowPackagesFile, json_encode($flowPackages));
		$this->checkForNewPackages($flowPackages);
	}

	public function filterFlowPackages($packages) {
		$flowPackages = array();
		foreach ($packages as $packageVersions) {
			$packageVersions = get_object_vars($packageVersions);
			foreach ($packageVersions as $version => $package) {
				if (stristr($package->type, "typo3-flow") || preg_match("/^typo3\//", $package->name)) {
					if (!isset($flowPackages[$package->name]) || $this->versionNewer($flowPackages[$package->name], $package)) {
						#$package->versions = $packageVersions;
						$flowPackages[$package->name] = clone $package;
					}
				}
			}
		}
		return $flowPackages;
	}

	public function versionNewer($package1, $package2) {
		$datetime1 = new \DateTime($package1->time);
		$datetime2 = new \DateTime($package2->time);
		return $datetime1 < $datetime2;
	}

	public function checkForNewPackages($packages) {
		$knownPackagesFile = FLOW_PATH_DATA . 'Packages/knownPackagesFile.json';

		if (file_exists($knownPackagesFile)) {
			$knownPackages = json_decode(file_get_contents($knownPackagesFile));
		} else {
			$knownPackages = array();
		}

		$newPackages = array();
		foreach ($packages as $package) {
			if (preg_match("/^typo3\//", $package->name)) {
				if (!in_array($package->name, $knownPackages)){
					$newPackages[] = $package->name;
					$knownPackages[] = $package->name;
				}
			}
		}

		if (count($newPackages) > 0) {
			$body = "There's a new package on packagist using the TYPO3 vendor namespace: \n\n";
			foreach ($newPackages as $newPackage) {
				$body.= $newPackage . "\n";
			}
			$receipients = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Famelo.PackageCatalog.Receipients');
			if (count($receipients) > 0) {
				$mail = new \TYPO3\SwiftMailer\Message();
				$mail
					->setFrom(array("server@famelo.com" => "Famelo.PackageCatalog"))
					->setTo($receipients)
					->setSubject("New TYPO3 Packages on packagist")
					->setBody($body, 'text/plain')
					->addPart(str_replace("\n", "<br />", $body), 'text/html')
					->send();
			}
		}

		file_put_contents($knownPackagesFile, json_encode($knownPackages));
	}
}

?>