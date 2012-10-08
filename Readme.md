*TYPO3.PackageCatalog*

This package fetches the available packages from packagist.org and "http://ci.typo3.robertlemke.net/job/composer-packages/ws/repository/packages.json" through an commandController and outputs a simple catalog with search functionality for the filtered typo3-flow-* type packages.

To update the packages run this command:

```
./flow packagecatalog:update
```

Then you can view the packages through:

```
http://[domain]/TYPO3.PackageCatalog/
```