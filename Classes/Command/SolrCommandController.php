<?php
namespace Kitzberger\SolrGc\Command;

use \TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Log\LogLevel;

class SolrCommandController extends CommandController
{
	/** @var \TYPO3\CMS\Core\Log\Logger */
	protected $logger = null;

	public function __construct()
	{
		$this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
	}

	/**
	 * Look for records in TYPO3 DB and remove them from solr index if they match a given where clause.
	 *
	 * @param string $table
	 * @param string $where
	 * @param boolean $dryRun
	 */
	public function garbageCollectCommand($table, $where = 'endtime < UNIX_TIMESTAMP()', $dryRun = false)
	{
		if ($table) {
			$records = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $table, $where);

			if ($GLOBALS['TYPO3_DB']->sql_error()) {
				$this->log($GLOBALS['TYPO3_DB']->sql_error(), LogLevel::ERROR);
				return false;
			}

			foreach ($records as $record) {
				if ($dryRun) {
					$this->log('[DRY-RUN] Removing ' . $table . ':' . $record['uid'] . ' from solr index.');
				} else {
					$this->log('Removing ' . $table . ':' . $record['uid'] . ' from solr index.');
					$this->deleteIndexDocuments($table, $record['uid']);
				}
			}
		}
	}

	/**
	 * @param  string $str
	 * @param  int $level
	 */
	protected function log($str, $level = LogLevel::INFO)
	{
		$this->logger->log($level, $str);
	}

	/**
	 * Deletes index documents for a given record identification.
	 *
	 * @param string $table The record's table name.
	 * @param integer $uid The record's uid.
	 */
	protected function deleteIndexDocuments($table, $uid)
	{
		$indexQueue        = GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');
		$connectionManager = GeneralUtility::makeInstance('Tx_Solr_ConnectionManager');

		// record can be indexed for multiple sites
		$indexQueueItems = $indexQueue->getItems($table, $uid);

		foreach ($indexQueueItems as $indexQueueItem) {
			$site = $indexQueueItem->getSite();

			// a site can have multiple connections (cores / languages)
			$solrConnections = $connectionManager->getConnectionsBySite($site);
			foreach ($solrConnections as $solr) {
				$solr->deleteByQuery('type:' . $table . ' AND uid:' . intval($uid));
				$solr->commit(FALSE, FALSE, FALSE);
			}
		}

		$indexQueue->deleteItem($table, $uid);
	}
}
