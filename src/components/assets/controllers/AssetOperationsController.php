<?php
namespace Blocks;

/**
 * Handles asset indexing and sizing tasks
 */
class AssetOperationsController extends BaseController
{
	/**
	 * Get an indexing session ID
	 */
	public function actionGetSessionId()
	{
		$this->requireLogin();
		$this->requireAjaxRequest();

		$this->returnJson(array('sessionId' => blx()->assetIndexing->getIndexingSessionId()));
	}

	/**
	 * Start indexing.
	 */
	public function actionStartIndex()
	{
		$this->requireLogin();
		$this->requireAjaxRequest();

		$sourceId = blx()->request->getRequiredPost('sourceId');
		$sessionId = blx()->request->getRequiredPost('session');

		if (blx()->request->getPost('doIndexes'))
		{
			// We have to do the indexing - get the actual list from the disk
			$this->returnJson(blx()->assetIndexing->getIndexListForSource($sessionId, $sourceId));
		}
		else
		{
			// Just the transformations, so get the indexed file list.
			$this->returnJson(array(
				'sourceId' => 	$sourceId,
				'total' => blx()->assets->getTotalFiles(array('sourceId' => $sourceId))
			));
		}
	}

	/**
	 * Do the indexing.
	 */
	public function actionPerformIndex()
	{
		$this->requireLogin();
		$this->requireAjaxRequest();

		$sourceId = blx()->request->getRequiredPost('sourceId');
		$sessionId = blx()->request->getRequiredPost('session');
		$offset = blx()->request->getRequiredPost('offset');

		if (blx()->request->getPost('doIndexes'))
		{
			$fileId = blx()->assetIndexing->processIndexForSource($sessionId, $offset, $sourceId);
			$return = array('success' => (bool) $fileId);
		}

		// Do the transformation update
		$transformationsToUpdate = blx()->request->getPost('doTransformations');
		if ($transformationsToUpdate)
		{
			// Did indexing already fill this one for us?
			if (empty($fileId))
			{
				// Okay, let's get the file from the file list, then.
				$file = blx()->assets->findFile(array('sourceId' => $sourceId , 'offset' => $offset));
			}
			else
			{
				$file = blx()->assets->getFileById($fileId);
			}

			if ($file instanceof AssetFileModel)
			{
				if (blx()->assetTransformations->updateTransformations($file, $transformationsToUpdate))
				{
					$return = array('success' => true);
				}
			}
		}

		if (empty($return))
		{
			$this->returnErrorJson(Blocks::t("Blocks couldn't find the requested file."));
		}
		else
		{
			$this->returnJson($return);
		}
	}

	/**
	 * Finish the indexing.
	 */
	public function actionFinishIndex()
	{
		$this->requireLogin();
		$this->requireAjaxRequest();

		$sources = blx()->request->getRequiredPost('sources');
		$command = blx()->request->getRequiredPost('command');
		$sessionId = blx()->request->getRequiredPost('sessionId');

		$sources = explode(",", $sources);

		$this->returnJson(blx()->assetIndexing->finishIndex($sessionId, $sources, $command));
	}
}
