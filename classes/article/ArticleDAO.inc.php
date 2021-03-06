<?php

/**
 * @file classes/article/ArticleDAO.inc.php
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArticleDAO
 * @ingroup article
 * @see Article
 *
 * @brief Operations for retrieving and modifying Article objects.
 */


import('classes.article.Article');
import('classes.submission.common.Action');

class ArticleDAO extends DAO {
	var $authorDao;

	var $cache;

	function _cacheMiss(&$cache, $id) {
		$article =& $this->getArticle($id, null, false);
		$cache->setCache($id, $article);
		return $article;
	}

	function &_getCache() {
		if (!isset($this->cache)) {
			$cacheManager =& CacheManager::getManager();
			$this->cache =& $cacheManager->getObjectCache('articles', 0, array(&$this, '_cacheMiss'));
		}
		return $this->cache;
	}

	/**
	 * Constructor.
	 */
	function ArticleDAO() {
		parent::DAO();
		$this->authorDao =& DAORegistry::getDAO('AuthorDAO');
	}

	/**
	 * Get a list of field names for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		/************************************************
		 * Edited by:  Anne Ivy Mirasol -- added fields
		 * Last Updated: 
                 *                Apr 17, 2012 -- returned objectives (spf)
                 *                May 4, 2011 -- added fields
		 ************************************************/
		return array(
			'title', 'cleanTitle', 'abstract', 'coverPageAltText', 'showCoverPage', 'hideCoverPageToc', 'hideCoverPageAbstract', 'originalFileName', 'fileName', 'width', 'height',
			'discipline', 'subjectClass', 'subject', 'coverageGeo', 'coverageChron', 'coverageSample', 'type', 'sponsor',
                        'objectives', 'keywords', 'startDate', 'endDate', 'fundsRequired', 'withHumanSubjects', 'proposalType', 'proposalCountry',
                        'technicalUnit', 'submittedAsPi', 'conflictOfInterest', 'reviewedByOtherErc', 'otherErcDecision', 'rtoOffice', 'whoId', 'reasonsForExemption','withdrawReason', 'withdrawComments',

						'approvalDate'
                        );
	}

	/**
	 * Update the settings for this object
	 * @param $article object
	 */
	function updateLocaleFields(&$article) {
		$this->updateDataObjectSettings('article_settings', $article, array(
			'article_id' => $article->getId()
		));
	}

	/**
	 * Retrieve an article by ID.
	 * @param $articleId int
	 * @param $journalId int optional
	 * @param $useCache boolean optional
	 * @return Article
	 */
	function &getArticle($articleId, $journalId = null, $useCache = false) {
		if ($useCache) {
			$cache =& $this->_getCache();
			$returner = $cache->get($articleId);
			if ($returner && $journalId != null && $journalId != $returner->getJournalId()) $returner = null;
			return $returner;
		}

		$primaryLocale = Locale::getPrimaryLocale();
		$locale = Locale::getLocale();
		$params = array(
			'title',
		$primaryLocale,
			'title',
		$locale,
			'abbrev',
		$primaryLocale,
			'abbrev',
		$locale,
		$articleId
		);
		$sql = 'SELECT	a.*,
				COALESCE(stl.setting_value, stpl.setting_value) AS section_title,
				COALESCE(sal.setting_value, sapl.setting_value) AS section_abbrev
			FROM	articles a
				LEFT JOIN sections s ON s.section_id = a.section_id
				LEFT JOIN section_settings stpl ON (s.section_id = stpl.section_id AND stpl.setting_name = ? AND stpl.locale = ?)
				LEFT JOIN section_settings stl ON (s.section_id = stl.section_id AND stl.setting_name = ? AND stl.locale = ?)
				LEFT JOIN section_settings sapl ON (s.section_id = sapl.section_id AND sapl.setting_name = ? AND sapl.locale = ?)
				LEFT JOIN section_settings sal ON (s.section_id = sal.section_id AND sal.setting_name = ? AND sal.locale = ?)
			WHERE	article_id = ?';
		if ($journalId !== null) {
			$sql .= ' AND a.journal_id = ?';
			$params[] = $journalId;
		}

		$result =& $this->retrieve($sql, $params);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnArticleFromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Internal function to return an Article object from a row.
	 * @param $row array
	 * @return Article
	 */
	function &_returnArticleFromRow(&$row) {
		$article = new Article();
		$this->_articleFromRow($article, $row);
		return $article;
	}

	/**
	 * Internal function to fill in the passed article object from the row.
	 * @param $article Article output article
	 * @param $row array input row
	 */
	function _articleFromRow(&$article, &$row) {
		$article->setId($row['article_id']);
		$article->setLocale($row['locale']);
		$article->setUserId($row['user_id']);
		$article->setJournalId($row['journal_id']);
		$article->setSectionId($row['section_id']);
		$article->setSectionTitle($row['section_title']);
		$article->setSectionAbbrev($row['section_abbrev']);
		$article->setStoredDOI($row['doi']);
		$article->setLanguage($row['language']);
		$article->setCommentsToEditor($row['comments_to_ed']);
		$article->setCitations($row['citations']);
		$article->setDateSubmitted($this->datetimeFromDB($row['date_submitted']));
		$article->setDateStatusModified($this->datetimeFromDB($row['date_status_modified']));
		$article->setLastModified($this->datetimeFromDB($row['last_modified']));
		$article->setStatus($row['status']);

		//Added by Anne Ivy Mirasol, May 18, 2011
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$article->setProposalStatus($articleDao->getProposalStatus($article->getId()));

		$article->setSubmissionProgress($row['submission_progress']);
		$article->setCurrentRound($row['current_round']);
		$article->setSubmissionFileId($row['submission_file_id']);
		$article->setRevisedFileId($row['revised_file_id']);
		$article->setReviewFileId($row['review_file_id']);
		$article->setEditorFileId($row['editor_file_id']);
		$article->setPages($row['pages']);
		$article->setFastTracked($row['fast_tracked']);
		$article->setHideAuthor($row['hide_author']);
		$article->setCommentsStatus($row['comments_status']);

		$article->setAuthors($this->authorDao->getAuthorsByArticle($row['article_id']));

		$this->getDataObjectSettings('article_settings', 'article_id', $row['article_id'], $article);

		HookRegistry::call('ArticleDAO::_returnArticleFromRow', array(&$article, &$row));

	}

	/**
	 * Insert a new Article.
	 * @param $article Article
	 */
	function insertArticle(&$article) {
		$article->stampModified();
		$this->update(
		sprintf('INSERT INTO articles
				(locale, user_id, journal_id, section_id, language, comments_to_ed, citations, date_submitted, date_status_modified, last_modified, status, submission_progress, current_round, submission_file_id, revised_file_id, review_file_id, editor_file_id, pages, fast_tracked, hide_author, comments_status, doi)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, %s, %s, %s, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		$this->datetimeToDB($article->getDateSubmitted()), $this->datetimeToDB($article->getDateStatusModified()), $this->datetimeToDB($article->getLastModified())),
		array(
		$article->getLocale(),
		$article->getUserId(),
		$article->getJournalId(),
		$article->getSectionId(),
		$article->getLanguage(),
		$article->getCommentsToEditor(),
		$article->getCitations(),
		$article->getStatus() === null ? STATUS_QUEUED : $article->getStatus(),
		$article->getSubmissionProgress() === null ? 1 : $article->getSubmissionProgress(),
		$article->getCurrentRound() === null ? 1 : $article->getCurrentRound(),
		$article->getSubmissionFileId(),
		$article->getRevisedFileId(),
		$article->getReviewFileId(),
		$article->getEditorFileId(),
		$article->getPages(),
		$article->getFastTracked()?1:0,
		$article->getHideAuthor() === null ? 0 : $article->getHideAuthor(),
		$article->getCommentsStatus() === null ? 0 : $article->getCommentsStatus(),
		$article->getStoredDOI()
		)
		);

		$article->setId($this->getInsertArticleId());
		$this->updateLocaleFields($article);

		// Insert authors for this article
		$authors =& $article->getAuthors();
		for ($i=0, $count=count($authors); $i < $count; $i++) {
			$authors[$i]->setSubmissionId($article->getId());
			$this->authorDao->insertAuthor($authors[$i]);
		}

		return $article->getId();
	}

	/**
	 * Update an existing article.
	 * @param $article Article
	 */
	function updateArticle(&$article) {
		$article->stampModified();
		$this->update(
		sprintf('UPDATE articles
				SET	locale = ?,
					user_id = ?,
					section_id = ?,
					language = ?,
					comments_to_ed = ?,
					citations = ?,
					date_submitted = %s,
					date_status_modified = %s,
					last_modified = %s,
					status = ?,
					submission_progress = ?,
					current_round = ?,
					submission_file_id = ?,
					revised_file_id = ?,
					review_file_id = ?,
					editor_file_id = ?,
					pages = ?,
					fast_tracked = ?,
					hide_author = ?,
					comments_status = ?,
					doi = ?
				WHERE article_id = ?',
		$this->datetimeToDB($article->getDateSubmitted()), $this->datetimeToDB($article->getDateStatusModified()), $this->datetimeToDB($article->getLastModified())),
		array(
		$article->getLocale(),
		$article->getUserId(),
		$article->getSectionId(),
		$article->getLanguage(),
		$article->getCommentsToEditor(),
		$article->getCitations(),
		$article->getStatus(),
		$article->getSubmissionProgress(),
		$article->getCurrentRound(),
		$article->getSubmissionFileId(),
		$article->getRevisedFileId(),
		$article->getReviewFileId(),
		$article->getEditorFileId(),
		$article->getPages(),
		$article->getFastTracked(),
		$article->getHideAuthor(),
		$article->getCommentsStatus(),
		$article->getStoredDOI(),
		$article->getId()
		)
		);

		$this->updateLocaleFields($article);

		// update authors for this article
		$authors =& $article->getAuthors();
		for ($i=0, $count=count($authors); $i < $count; $i++) {
			if ($authors[$i]->getId() > 0) {
				$this->authorDao->updateAuthor($authors[$i]);
			} else {
				$this->authorDao->insertAuthor($authors[$i]);
			}
		}

		// Remove deleted authors
		$removedAuthors = $article->getRemovedAuthors();
		for ($i=0, $count=count($removedAuthors); $i < $count; $i++) {
			$this->authorDao->deleteAuthorById($removedAuthors[$i], $article->getId());
		}

		// Update author sequence numbers
		$this->authorDao->resequenceAuthors($article->getId());

		$this->flushCache();
	}

	/**
	 * Delete an article.
	 * @param $article Article
	 */
	function deleteArticle(&$article) {
		return $this->deleteArticleById($article->getId());
	}

	/**
	 * Delete an article by ID.
	 * @param $articleId int
	 */
	function deleteArticleById($articleId) {
		$this->authorDao->deleteAuthorsByArticle($articleId);

		$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
		$publishedArticleDao->deletePublishedArticleByArticleId($articleId);

		$commentDao =& DAORegistry::getDAO('CommentDAO');
		$commentDao->deleteBySubmissionId($articleId);

		$noteDao =& DAORegistry::getDAO('NoteDAO');
		$noteDao->deleteByAssoc(ASSOC_TYPE_ARTICLE, $articleId);

		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$sectionEditorSubmissionDao->deleteDecisionsByArticle($articleId);
		$sectionEditorSubmissionDao->deleteReviewRoundsByArticle($articleId);

		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignmentDao->deleteReviewAssignmentsByArticle($articleId);

		$editAssignmentDao =& DAORegistry::getDAO('EditAssignmentDAO');
		$editAssignmentDao->deleteEditAssignmentsByArticle($articleId);

		// Delete copyedit, layout, and proofread signoffs
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$copyedInitialSignoffs = $signoffDao->getBySymbolic('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $articleId);
		$copyedAuthorSignoffs = $signoffDao->getBySymbolic('SIGNOFF_COPYEDITING_AUTHOR', ASSOC_TYPE_ARTICLE, $articleId);
		$copyedFinalSignoffs = $signoffDao->getBySymbolic('SIGNOFF_COPYEDITING_FINAL', ASSOC_TYPE_ARTICLE, $articleId);
		$layoutSignoffs = $signoffDao->getBySymbolic('SIGNOFF_LAYOUT', ASSOC_TYPE_ARTICLE, $articleId);
		$proofreadAuthorSignoffs = $signoffDao->getBySymbolic('SIGNOFF_PROOFREADING_AUTHOR', ASSOC_TYPE_ARTICLE, $articleId);
		$proofreadProofreaderSignoffs = $signoffDao->getBySymbolic('SIGNOFF_PROOFREADING_PROOFREADER', ASSOC_TYPE_ARTICLE, $articleId);
		$proofreadLayoutSignoffs = $signoffDao->getBySymbolic('SIGNOFF_PROOFREADING_LAYOUT', ASSOC_TYPE_ARTICLE, $articleId);
		$signoffs = array($copyedInitialSignoffs, $copyedAuthorSignoffs, $copyedFinalSignoffs, $layoutSignoffs,
		$proofreadAuthorSignoffs, $proofreadProofreaderSignoffs, $proofreadLayoutSignoffs);
		foreach ($signoffs as $signoff) {
			if ( $signoff ) $signoffDao->deleteObject($signoff);
		}

		$articleCommentDao =& DAORegistry::getDAO('ArticleCommentDAO');
		$articleCommentDao->deleteArticleComments($articleId);

		$articleGalleyDao =& DAORegistry::getDAO('ArticleGalleyDAO');
		$articleGalleyDao->deleteGalleysByArticle($articleId);

		$articleSearchDao =& DAORegistry::getDAO('ArticleSearchDAO');
		$articleSearchDao->deleteArticleKeywords($articleId);

		$articleEventLogDao =& DAORegistry::getDAO('ArticleEventLogDAO');
		$articleEventLogDao->deleteArticleLogEntries($articleId);

		$articleEmailLogDao =& DAORegistry::getDAO('ArticleEmailLogDAO');
		$articleEmailLogDao->deleteArticleLogEntries($articleId);

		$articleEventLogDao =& DAORegistry::getDAO('ArticleEventLogDAO');
		$articleEventLogDao->deleteArticleLogEntries($articleId);

		$suppFileDao =& DAORegistry::getDAO('SuppFileDAO');
		$suppFileDao->deleteSuppFilesByArticle($articleId);

		// Delete article files -- first from the filesystem, then from the database
		import('classes.file.ArticleFileManager');
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
		$articleFiles =& $articleFileDao->getArticleFilesByArticle($articleId);

		$articleFileManager = new ArticleFileManager($articleId);
		foreach ($articleFiles as $articleFile) {
			$articleFileManager->deleteFile($articleFile->getFileId());
		}

		$articleFileDao->deleteArticleFiles($articleId);

		// Delete article citations.
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		$citationDao->deleteObjectsByAssocId(ASSOC_TYPE_ARTICLE, $articleId);

		$this->update('DELETE FROM article_settings WHERE article_id = ?', $articleId);
		$this->update('DELETE FROM articles WHERE article_id = ?', $articleId);

		$this->flushCache();
	}

	/**
	 * Get all articles for a journal (or all articles in the system).
	 * @param $userId int
	 * @param $journalId int
	 * @return DAOResultFactory containing matching Articles
	 */
	function &getArticlesByJournalId($journalId = null) {
		$primaryLocale = Locale::getPrimaryLocale();
		$locale = Locale::getLocale();
		$articles = array();

		$params = array(
			'title',
		$primaryLocale,
			'title',
		$locale,
			'abbrev',
		$primaryLocale,
			'abbrev',
		$locale
		);
		if ($journalId !== null) $params[] = (int) $journalId;

		$result =& $this->retrieve(
			'SELECT	a.*,
				COALESCE(stl.setting_value, stpl.setting_value) AS section_title,
				COALESCE(sal.setting_value, sapl.setting_value) AS section_abbrev
			FROM	articles a
				LEFT JOIN sections s ON s.section_id = a.section_id
				LEFT JOIN section_settings stpl ON (s.section_id = stpl.section_id AND stpl.setting_name = ? AND stpl.locale = ?)
				LEFT JOIN section_settings stl ON (s.section_id = stl.section_id AND stl.setting_name = ? AND stl.locale = ?)
				LEFT JOIN section_settings sapl ON (s.section_id = sapl.section_id AND sapl.setting_name = ? AND sapl.locale = ?)
				LEFT JOIN section_settings sal ON (s.section_id = sal.section_id AND sal.setting_name = ? AND sal.locale = ?)
			' . ($journalId !== null ? 'WHERE a.journal_id = ?' : ''),
		$params
		);

		$returner = new DAOResultFactory($result, $this, '_returnArticleFromRow');
		return $returner;
	}

	/**
	 * Delete all articles by journal ID.
	 * @param $journalId int
	 */
	function deleteArticlesByJournalId($journalId) {
		$articles = $this->getArticlesByJournalId($journalId);

		while (!$articles->eof()) {
			$article =& $articles->next();
			$this->deleteArticleById($article->getId());
		}
	}

	/**
	 * Get all articles for a user.
	 * @param $userId int
	 * @param $journalId int optional
	 * @return array Articles
	 */
	function &getArticlesByUserId($userId, $journalId = null) {
		$primaryLocale = Locale::getPrimaryLocale();
		$locale = Locale::getLocale();
		$params = array(
			'title',
		$primaryLocale,
			'title',
		$locale,
			'abbrev',
		$primaryLocale,
			'abbrev',
		$locale,
		$userId
		);
		if ($journalId) $params[] = $journalId;
		$articles = array();

		$result =& $this->retrieve(
			'SELECT	a.*,
				COALESCE(stl.setting_value, stpl.setting_value) AS section_title,
				COALESCE(sal.setting_value, sapl.setting_value) AS section_abbrev
			FROM	articles a
				LEFT JOIN sections s ON s.section_id = a.section_id
				LEFT JOIN section_settings stpl ON (s.section_id = stpl.section_id AND stpl.setting_name = ? AND stpl.locale = ?)
				LEFT JOIN section_settings stl ON (s.section_id = stl.section_id AND stl.setting_name = ? AND stl.locale = ?)
				LEFT JOIN section_settings sapl ON (s.section_id = sapl.section_id AND sapl.setting_name = ? AND sapl.locale = ?)
				LEFT JOIN section_settings sal ON (s.section_id = sal.section_id AND sal.setting_name = ? AND sal.locale = ?)
			WHERE	a.user_id = ?' .
		(isset($journalId)?' AND a.journal_id = ?':''),
		$params
		);

		while (!$result->EOF) {
			$articles[] =& $this->_returnArticleFromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $articles;
	}

	/**
	 * Get the ID of the journal an article is in.
	 * @param $articleId int
	 * @return int
	 */
	function getArticleJournalId($articleId) {
		$result =& $this->retrieve(
			'SELECT journal_id FROM articles WHERE article_id = ?', $articleId
		);
		$returner = isset($result->fields[0]) ? $result->fields[0] : false;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Check if the specified incomplete submission exists.
	 * @param $articleId int
	 * @param $userId int
	 * @param $journalId int
	 * @return int the submission progress
	 */
	function incompleteSubmissionExists($articleId, $userId, $journalId) {
		$result =& $this->retrieve(
			'SELECT submission_progress FROM articles WHERE article_id = ? AND user_id = ? AND journal_id = ? AND date_submitted IS NULL',
		array($articleId, $userId, $journalId)
		);
		$returner = isset($result->fields[0]) ? $result->fields[0] : false;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Change the status of the article
	 * @param $articleId int
	 * @param $status int
	 */
	function changeArticleStatus($articleId, $status) {
		$this->update(
			'UPDATE articles SET status = ? WHERE article_id = ?', array((int) $status, (int) $articleId)
		);

		$this->flushCache();
	}

	/**
	 * Change the DOI of an article
	 * @param $articleId int
	 * @param $doi string
	 */
	function changeDOI($articleId, $doi) {
		$this->update(
			'UPDATE articles SET doi = ? WHERE article_id = ?', array($doi, (int) $articleId)
		);

		$this->flushCache();
	}

	function assignDOIs($forceReassign = false, $journalId = null) {
		if ($forceReassign) {
			$this->update(
				'UPDATE articles SET doi = null' . ($journalId !== null?' WHERE journal_id = ?':''),
			$journalId !== null?array((int) $journalId):false
			);
			$this->flushCache();
		}

		$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
		$articles =& $publishedArticleDao->getPublishedArticlesByJournalId($journalId);
		while ($article =& $articles->next()) {
			// Cause a DOI to be fetched and stored.
			$article->getDOI();
			unset($article);
		}

		$this->flushCache();
	}

	/**
	 * Removes articles from a section by section ID
	 * @param $sectionId int
	 */
	function removeArticlesFromSection($sectionId) {
		$this->update(
			'UPDATE articles SET section_id = null WHERE section_id = ?', $sectionId
		);

		$this->flushCache();
	}

	/**
	 * Get the ID of the last inserted article.
	 * @return int
	 */
	function getInsertArticleId() {
		return $this->getInsertId('articles', 'article_id');
	}

	function flushCache() {
		// Because both publishedArticles and articles are cached by
		// article ID, flush both caches on update.
		$cache =& $this->_getCache();
		$cache->flush();
		unset($cache);

		$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
		$cache =& $publishedArticleDao->_getPublishedArticleCache();
		$cache->flush();
		unset($cache);
	}

	/*******************************************************************************************
	 *  Added by:  Anne Ivy Mirasol
	 *  Last updated: April 25, 2011
	 ******************************************************************************************/

	/**
	 * Get all possible proposal types.
	 * @param none
	 * @return array proposalTypes
	 */
	function getProposalTypes() {
		$locale = Locale::getLocale();
		$filename = "lib/pkp/locale/".$locale."/proposaltypes.xml";

		$xmlDao = new XMLDAO();
		$data = $xmlDao->parseStruct($filename, array('proposaltypes', 'proposaltype'));

		$proposalTypes = array();
		if (isset($data['proposaltypes'])) {
			$i=0;
			foreach ($data['proposaltype'] as $proposalTypeData) {
				$proposalType['code'] = $proposalTypeData['attributes']['code'];
				$proposalType['name'] = $proposalTypeData['attributes']['name'];
				array_push($proposalTypes, $proposalType);
			}
			$i++;
		}


		return $proposalTypes;

	}
	
	/**********************************************************************
	 * Get proposal type by code
	 * Added by igm 9/28/11
         * Last updated Jan 30 2012, to support multiple proposal types
	 ***********************************************************************/
	function getProposalType($code) {
                $proposalTypeCodeArray = explode("+", $code);
                $proposalTypeTextArray = array();
                foreach($proposalTypeCodeArray as $ptypeCode) {
                    $typeText = $this->getProposalTypeSingle($ptypeCode);
                    array_push($proposalTypeTextArray, $typeText);
                }
                
                $proposalTypeText = "";
                foreach($proposalTypeTextArray as $i => $ptype) {
                    $proposalTypeText = $proposalTypeText . $ptype;
                    if($i < count($proposalTypeTextArray)-1) $proposalTypeText = $proposalTypeText . ", ";
                }

                return $proposalTypeText;
	}

        function getProposalTypeSingle($code) {
            $proposalTypes = $this->getProposalTypes();
            foreach($proposalTypes as $pt) {
                if ($pt['code'] == $code) {
                    return $pt['name'];
                }
            }
            return $code;
        }

	/**********************************************************************
	 *
	 * Get proposal status based on last decision on article
	 * Added by Gay Figueroa
	 * Last Update: 5/18/2011
	 *
	 ***********************************************************************/

	function getProposalStatus($articleId, $round = null) {
		$decision = $this->getLastEditorDecision($articleId, $round);
		switch ($decision['decision']) {
			case SUBMISSION_EDITOR_DECISION_EXEMPTED:
				$proposalStatus = PROPOSAL_STATUS_EXEMPTED;
				break;
			case SUBMISSION_EDITOR_DECISION_COMPLETE:
				$proposalStatus = PROPOSAL_STATUS_CHECKED;
				break;
			case SUBMISSION_EDITOR_DECISION_ASSIGNED:
				$proposalStatus = PROPOSAL_STATUS_ASSIGNED;
				break;
			case SUBMISSION_EDITOR_DECISION_EXPEDITED:
				$proposalStatus = PROPOSAL_STATUS_EXPEDITED;
				break;
			case SUBMISSION_EDITOR_DECISION_ACCEPT:
			case SUBMISSION_EDITOR_DECISION_RESUBMIT:
			case SUBMISSION_EDITOR_DECISION_DECLINE:
				$proposalStatus = PROPOSAL_STATUS_REVIEWED;
				break;
			case SUBMISSION_EDITOR_DECISION_INCOMPLETE:
				$proposalStatus = PROPOSAL_STATUS_RETURNED;
				break;
			default:
				$proposalStatus=PROPOSAL_STATUS_SUBMITTED;
				break;
		}
		return $proposalStatus;
	}

	/**********************************************************************
	 *
	 * Get last decision on article
	 * Added by Gay Figueroa
	 * Last Update: 5/18/2011
	 *
	 * Updated: 5/24/2011 - After adding GROUP BY, it always returns first decision instead of last;
	 *                      Removed MAX and GROUP BY since it will always take the last row anyway
	 ***********************************************************************/

	function getLastEditorDecision($articleId, $round = null) {
		if ($round == null) {
			$result =& $this->retrieve(
				'SELECT edit_decision_id, editor_id, decision, date_decided, resubmit_count FROM edit_decisions WHERE article_id = ? ORDER BY edit_decision_id ASC', $articleId
			);

		} else {
			$result =& $this->retrieve(
				'SELECT edit_decision_id, editor_id, decision, date_decided, resubmit_count FROM edit_decisions WHERE article_id = ? AND round = ? ORDER BY edit_decision_id ASC',
			array($articleId, $round)
			);
		}

		$resultArray = $result->GetArray();
		$last = count($resultArray) - 1;

		$result->Close();
		unset($result);

                if($last == -1) return null; //AIM, 12.12.2011

		$decision = array("editDecisionId" => $resultArray[$last]['edit_decision_id'], "editorId" => $resultArray[$last]['editor_id'], "decision" => $resultArray[$last]['decision'], "dateDecided" => $resultArray[$last]['date_decided'], "resubmitCount" => $resultArray[$last]['resubmit_count']);
		return 	$decision;
	}


	/*******************************************************************************************/
	/*
	 *  Added by:  Anne Ivy Mirasol
	 *  Last updated: May 4, 2011
	 *
	 ******************************************************************************************/

	/**
	 * Get the number of submissions for the year.
	 * @param $year
	 * @return integer
	 */
	function getSubmissionsForYearCount($year) {
		$result =& $this->retrieve('SELECT * FROM articles where date_submitted is not null and extract(year from date_submitted) = ?', $year);
		$count = $result->NumRows();

		return $count;
	}


	/**
	 * Get the number of submissions for the country for the year.
	 * @param $year
	 * @return integer
	 *
	 */
	function getSubmissionsForYearForCountryCount($year, $country) {
		$result =& $this->retrieve('SELECT * FROM articles
                                            WHERE date_submitted is not NULL and extract(year from date_submitted) = ? and
                                            article_id in (SELECT article_id from article_settings where setting_name = ? and setting_value = ?)',
		array($year, 'proposalCountry', $country));

		$count = $result->NumRows();

		return $count;
	}


        /**
	 * Get the number of submissions for the country for the year.
	 * @param $year
	 * @return integer
	 *
         * Added 12.25.2011
	 */
	function getICPSubmissionsForYearCount($year) {
		$result =& $this->retrieve('SELECT * FROM articles
                                            WHERE date_submitted is not NULL and extract(year from date_submitted) = ? and
                                            article_id in (SELECT article_id from article_settings where setting_name = ? and setting_value LIKE ?)',
		array($year, 'whoId', '%.ICP.%'));

		$count = $result->NumRows();

		return $count;
	}


	/************************************
	 * Added by: Anne Ivy Mirasol
	 * Last Updated: May 18, 2011
	 * Reset an article's progress
	 ************************************/

	function changeArticleProgress($articleId, $step) {
		$this->update(
			'UPDATE articles SET submission_progress = ? WHERE article_id = ?', array((int) $step, (int) $articleId)
		);

		$this->flushCache();
	}


	/**
	 *  Added by:  Anne Ivy Mirasol
	 *  Last Updated: May 24, 2011
	 *
	 *  Compare decision date and submission date of proposal to determine if proposal has been resubmitted
	 *  @return boolean
	 */
	function isProposalResubmitted($articleId) {
		$result =& $this->retrieve('SELECT date_submitted FROM articles WHERE article_id = ?', $articleId);
		$row = $result->FetchRow();
		$date_submitted = $row['date_submitted'];


		$result =& $this->retrieve('SELECT date_decided FROM edit_decisions WHERE edit_decision_id =
                                             (SELECT MAX(edit_decision_id) FROM edit_decisions WHERE article_id = ? GROUP BY article_id)', $articleId);
		$row = $result->FetchRow();
		$date_decided = $row['date_decided'];

		if($date_decided < $date_submitted) return true;
		else return false;
	}

	/**
	 *  Added by:  Anne Ivy Mirasol
	 *  Last Updated: June 15, 2011
	 *
	 *  Set status in articles table to PROPOSAL_STATUS_WITHDRAWN
	 *  @return boolean
	 */

	function withdrawProposal($articleId) {
		$this->update(
			'UPDATE articles SET status = ? WHERE article_id = ?', array(PROPOSAL_STATUS_WITHDRAWN, (int) $articleId)
		);

		$this->flushCache();
	}

	/**
	 *  Added by:  Anne Ivy Mirasol
	 *  Last Updated: June 15, 2011
	 *
	 *  Set status in articles table to PROPOSAL_STATUS_ARCHIVED
	 *  @return boolean
	 */

	function sendToArchive($articleId) {
		$this->update(
			'UPDATE articles SET status = ? WHERE article_id = ?', array(PROPOSAL_STATUS_ARCHIVED, (int) $articleId)
		);
		$this->flushCache();
	}
	
	/**
	 * Insert reasons for exemption in article_settings
	 * @return articleId int
	 * Added by aglet
	 * Last Update: 6/21/2011
	 */
	function insertReasonsForExemption($article, $reasons) {
		$this->update('INSERT INTO article_settings (article_id, locale, setting_name, setting_value, setting_type) values (?, ?, ?, ?, ?)', array($article->getId(), $article->getLocale(), 'reasonsForExemption', (int) $reasons, 'int')
		);
		$this->flushCache();
		return $article->getId();
	}


        /**
         *  Added by:  Anne Ivy Mirasol
         *  Last Updated: June 22, 2011
         *
         *  Set status in articles table to PROPOSAL_STATUS_COMPLETED
         *  @return boolean
         */

        function setAsCompleted($articleId) {
            $this->update(
			'UPDATE articles SET status = ? WHERE article_id = ?', array(PROPOSAL_STATUS_COMPLETED, (int) $articleId)
		);

            $this->flushCache();
        }
        
	/**
	 * Insert approvalDate in article_settings
	 * @return articleId int
	 * Added by aglet
	 * Last Update: 7/21/2011
	 */
	function insertApprovalDate($article, $approvalDate) {
		$result =& $this->retrieve('SELECT setting_value FROM article_settings WHERE article_id = ? and setting_name = ? and locale = ? LIMIT 1', array($article->getId(), 'approvalDate', $article->getLocale()));
		$row = $result->FetchRow();
		$existing = ($row['setting_value'] != null ? true : false);
		if(!$existing) {
			$this->update(sprintf('INSERT INTO article_settings 
			(article_id, locale, setting_name, setting_value, setting_type) 
			values (?, ?, ?, %s, ?)',$this->datetimeToDB($approvalDate)), array($article->getId(), $article->getLocale(), 'approvalDate', 'string')
			);
		}
		else {
			$this->update(sprintf('UPDATE article_settings SET setting_value = %s WHERE 
			article_id = ? and locale = ? and setting_name = ?',
			$this->datetimeToDB($approvalDate)), array($article->getId(), $article->getLocale(), 'approvalDate')
			);
		}
		$this->flushCache();
		return $article->getId();
	}
	
	function searchProposalsPublic($query, $dateFrom, $dateTo) {
		$searchSql = "select distinct a.article_id, whoid.setting_value as whoid, a.date_submitted as date_submitted, title.setting_value as title, 
					  country.setting_value as country, author.first_name as afname, author.last_name as alname, author.email as email,
					  editor.first_name as efname, editor.last_name as elname, startDate.setting_value as start_date, 
					  endDate.setting_value as end_date, keywords.setting_value as keywords, ed.decision, ed.date_decided
				  FROM articles a
				inner join article_settings whoid on (a.article_id = whoid.article_id and whoid.setting_name = 'whoId')
				inner join article_settings title on (a.article_id = title.article_id and title.setting_name = 'title')
				inner join article_settings country on (a.article_id = country.article_id and country.setting_name = 'proposalCountry')
				inner join article_settings keywords on (a.article_id = keywords.article_id and keywords.setting_name = 'keywords')
				inner join article_settings startDate on (a.article_id = startDate.article_id and startDate.setting_name = 'startDate')
				inner join article_settings endDate on (a.article_id = endDate.article_id and endDate.setting_name = 'endDate')
				inner join users as author on (author.user_id = a.user_id)
				inner join edit_decisions as ed on (ed.article_id = a.article_id)
				inner join users as editor on (editor.user_id = ed.editor_id)
				where (ed.decision = '1' or ed.decision = '2' or ed.decision = '3')";
		
		if (!empty($query)) {
			$searchSql .= " AND (keywords.setting_value LIKE '"."%".$query."%"."' or title.setting_value LIKE '"."%".$query."%"."')";
		}
	
		if (!empty($dateFrom) || !empty($dateTo)){
			if (!empty($dateFrom)) {
				$searchSql .= ' AND a.date_submitted >= ' . $this->datetimeToDB($dateFrom);
			}
			if (!empty($dateTo)) {
				$searchSql .= ' AND a.date_submitted <= ' . $this->datetimeToDB($dateTo);
			}
		}
		$result =& $this->retrieve($searchSql);
		
		while (!$result->EOF) {
			$articles[] =& $this->_returnSearchArticleFromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $articles;
	}
	
/**
	 * Internal function to return an Article object from a row.
	 * @param $row array
	 * @return Article
	 */
	function &_returnSearchArticleFromRow(&$row) {
		$article = new Article();
		$this->_searchArticleFromRow($article, $row);
		return $article;
	}

	/**
	 * Internal function to fill in the passed article object from the row.
	 * @param $article Article output article
	 * @param $row array input row
	 */
	function _searchArticleFromRow(&$article, &$row) {
		$article->setId($row['article_id']);
		$article->setWhoId($row['whoid']);
		$article->setDateSubmitted($this->datetimeFromDB($row['date_submitted']));
		$article->setTitle($row['title']);
		$article->setProposalCountry($row['country']);
		$article->setPrimaryEditor($row['efname']." ".$row['elname']);
		$article->setPrimaryAuthor($row['afname']." ".$row['alname']);
		$article->setStartDate($this->datetimeFromDB($row['start_date']));
		$article->setEndDate($this->datetimeFromDB($row['end_date']));
		$article->setProposalStatus($row['decision']);
		$article->setDateStatusModified($this->datetimeFromDB($row['date_decided']));
		$article->setAuthorEmail($row['email']);
		
		HookRegistry::call('ArticleDAO::_returnSearchArticleFromRow', array(&$article, &$row));

	}
}

?>
