<?php

if (TYPO3_MODE === 'BE' || TYPO3_MODE === 'CLI') {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers']['SolrGc-Solr'] =
		\Kitzberger\SolrGc\Command\SolrCommandController::class;
}
