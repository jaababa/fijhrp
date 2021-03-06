<?php

/**
 * @file classes/submission/sectionEditor/SectionEditorAction.inc.php
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SectionEditorAction
 * @ingroup submission
 *
 * @brief SectionEditorAction class.
 */

// $Id$


import('classes.submission.common.Action');

class SectionEditorAction extends Action {

	/**
	 * Constructor.
	 */
	function SectionEditorAction() {
		parent::Action();
	}

	/**
	 * Actions.
	 */

	/**
	 * Changes the section an article belongs in.
	 * @param $sectionEditorSubmission int
	 * @param $sectionId int
	 */
	function changeSection($sectionEditorSubmission, $sectionId) {
		if (!HookRegistry::call('SectionEditorAction::changeSection', array(&$sectionEditorSubmission, $sectionId))) {
			$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
			$sectionEditorSubmission->setSectionId($sectionId);
			$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);
		}
	}

	/**
	 * Records an editor's submission decision.
	 * @param $sectionEditorSubmission object
	 * @param $decision int

	 function recordDecision($sectionEditorSubmission, $decision) {
		$editAssignments =& $sectionEditorSubmission->getEditAssignments();
		if (empty($editAssignments)) return;

		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$user =& Request::getUser();
		$editorDecision = array(
		'editDecisionId' => null,
		'editorId' => $user->getId(),
		'decision' => $decision,
		'dateDecided' => date(Core::getCurrentDate())
		);

		if (!HookRegistry::call('SectionEditorAction::recordDecision', array(&$sectionEditorSubmission, $editorDecision))) {
		$sectionEditorSubmission->setStatus(STATUS_QUEUED);
		$sectionEditorSubmission->stampStatusModified();
		$sectionEditorSubmission->addDecision($editorDecision, $sectionEditorSubmission->getCurrentRound());
		$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);

		$decisions = SectionEditorSubmission::getEditorDecisionOptions();
		// Add log
		import('classes.article.log.ArticleLog');
		import('classes.article.log.ArticleEventLogEntry');
		Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON, LOCALE_COMPONENT_OJS_EDITOR));
		ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_EDITOR_DECISION, ARTICLE_LOG_TYPE_EDITOR, $user->getId(), 'log.editor.decision', array('editorName' => $user->getFullName(), 'articleId' => $sectionEditorSubmission->getArticleId(), 'decision' => Locale::translate($decisions[$decision])));
		}
		}
		*/

	/**
	 * Records an editor's submission decision. (Modified: Update if there is already an existing decision.)
	 * @param $sectionEditorSubmission object
	 * @param $decision int
	 * @param $lastDecisionId int (Added)
	 * Edited by Gay Figueroa
	 * Last Update: 5/4/2011
	 */
	function recordDecision($sectionEditorSubmission, $decision, $lastDecisionId = null, $resubmitCount, $dateDecided = null) {
		$editAssignments =& $sectionEditorSubmission->getEditAssignments();
		if (empty($editAssignments)) return;

		$user =& Request::getUser();
		$journal =& Request::getJournal();
		
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$articleDao =& DaoRegistry::getDAO('ArticleDAO');
		
		$currentDate = date(Core::getCurrentDate());
		$approvalDate = (($dateDecided == null) ? $currentDate : date($dateDecided));
		$resubmitCount = ($decision == SUBMISSION_EDITOR_DECISION_RESUBMIT || $decision == SUBMISSION_EDITOR_DECISION_INCOMPLETE) ? $resubmitCount + 1 : $resubmitCount ;
		$editorDecision = array(
				'editDecisionId' => $lastDecisionId,
				'editorId' => $user->getId(),
				'decision' => $decision,
				'dateDecided' => $currentDate,
				'resubmitCount' => $resubmitCount
		);

		/*
		 * Last Update: April 13, 2012
		 * If assigned for full erc review, do NOT automatically assign all users with REVIEWER role		 
		if($decision == SUBMISSION_EDITOR_DECISION_ASSIGNED) {
			$userDao =& DAORegistry::getDAO('UserDAO');
			$reviewers =& $userDao->getUsersWithReviewerRole($journal->getId());
			foreach($reviewers as $reviewer) {
				$reviewerId = $reviewer->getId();
				SectionEditorAction::addReviewer($sectionEditorSubmission, $reviewerId, $round = null);
			}
		}
		*/
		
		/*
		 * If approved, insert approvalDate
		 */
		if($decision == SUBMISSION_EDITOR_DECISION_ACCEPT) {
			$articleDao->insertApprovalDate($sectionEditorSubmission, $approvalDate);
		}
		
		if (!HookRegistry::call('SectionEditorAction::recordDecision', array(&$sectionEditorSubmission, $editorDecision))) {
			$sectionEditorSubmission->setStatus(STATUS_QUEUED);
			$sectionEditorSubmission->stampStatusModified();
			$sectionEditorSubmission->addDecision($editorDecision, $sectionEditorSubmission->getCurrentRound());
			if($decision == SUBMISSION_EDITOR_DECISION_DONE) {
				$sectionEditorSubmission->setStatus(PROPOSAL_STATUS_COMPLETED);
			}
			$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);

			$decisions = SectionEditorSubmission::getAllPossibleEditorDecisionOptions();
			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON, LOCALE_COMPONENT_OJS_EDITOR));
			ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_EDITOR_DECISION, ARTICLE_LOG_TYPE_EDITOR, $user->getId(), 'log.editor.decision', array('editorName' => $user->getFullName(), 'articleId' => $sectionEditorSubmission->getArticleId(), 'decision' => Locale::translate($decisions[$decision])));
		}
	}

	/**
	 * Assigns a reviewer to a submission.
	 * @param $sectionEditorSubmission object
	 * @param $reviewerId int
	 */
	function addReviewer($sectionEditorSubmission, $reviewerId, $round = null) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& Request::getUser();

		$reviewer =& $userDao->getUser($reviewerId);

		// Check to see if the requested reviewer is not already
		// assigned to review this article.
		if ($round == null) {
			$round = $sectionEditorSubmission->getCurrentRound();
		}

		$assigned = $sectionEditorSubmissionDao->reviewerExists($sectionEditorSubmission->getArticleId(), $reviewerId, $round);

		// Only add the reviewer if he has not already
		// been assigned to review this article.
		if (!$assigned && isset($reviewer) && !HookRegistry::call('SectionEditorAction::addReviewer', array(&$sectionEditorSubmission, $reviewerId))) {
			$reviewAssignment = new ReviewAssignment();
			$reviewAssignment->setReviewerId($reviewerId);
			$reviewAssignment->setDateAssigned(Core::getCurrentDate());
			$reviewAssignment->setRound($round);

			// Assign review form automatically if needed
			$journalId = $sectionEditorSubmission->getJournalId();
			$sectionDao =& DAORegistry::getDAO('SectionDAO');
			$reviewFormDao =& DAORegistry::getDAO('ReviewFormDAO');

			$sectionId = $sectionEditorSubmission->getSectionId();
			$section =& $sectionDao->getSection($sectionId, $journalId);
			if ($section && ($reviewFormId = (int) $section->getReviewFormId())) {
				if ($reviewFormDao->reviewFormExists($reviewFormId, ASSOC_TYPE_JOURNAL, $journalId)) {
					$reviewAssignment->setReviewFormId($reviewFormId);
				}
			}

			$sectionEditorSubmission->addReviewAssignment($reviewAssignment);
			$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);

			$reviewAssignment = $reviewAssignmentDao->getReviewAssignment($sectionEditorSubmission->getArticleId(), $reviewerId, $round);

			$journal =& Request::getJournal();
			$settingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
			$settings =& $settingsDao->getJournalSettings($journal->getId());
			if (isset($settings['numWeeksPerReview'])) SectionEditorAction::setDueDate($sectionEditorSubmission->getArticleId(), $reviewAssignment->getId(), null, $settings['numWeeksPerReview'], false);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_REVIEW_ASSIGN, ARTICLE_LOG_TYPE_REVIEW, $reviewAssignment->getId(), 'log.review.reviewerAssigned', array('reviewerName' => $reviewer->getFullName(), 'articleId' => $sectionEditorSubmission->getArticleId(), 'round' => $round));
		}
	}

	/**
	 * Clears a review assignment from a submission.
	 * @param $sectionEditorSubmission object
	 * @param $reviewId int
	 */
	function clearReview($sectionEditorSubmission, $reviewId) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);

		if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $sectionEditorSubmission->getArticleId() && !HookRegistry::call('SectionEditorAction::clearReview', array(&$sectionEditorSubmission, $reviewAssignment))) {
			$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());
			if (!isset($reviewer)) return false;
			$sectionEditorSubmission->removeReviewAssignment($reviewId);
			$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_REVIEW_CLEAR, ARTICLE_LOG_TYPE_REVIEW, $reviewAssignment->getId(), 'log.review.reviewCleared', array('reviewerName' => $reviewer->getFullName(), 'articleId' => $sectionEditorSubmission->getArticleId(), 'round' => $reviewAssignment->getRound()));
		}
	}

	/**
	 * Notifies a reviewer about a review assignment.
	 * @param $sectionEditorSubmission object
	 * @param $reviewId int
	 * @return boolean true iff ready for redirect
	 */
	function notifyReviewer($sectionEditorSubmission, $reviewId, $send = false) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');

		$journal =& Request::getJournal();
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);

		$isEmailBasedReview = $journal->getSetting('mailSubmissionsToReviewers')==1?true:false;
		$reviewerAccessKeysEnabled = $journal->getSetting('reviewerAccessKeysEnabled');

		// If we're using access keys, disable the address fields
		// for this message. (Prevents security issue: section editor
		// could CC or BCC someone else, or change the reviewer address,
		// in order to get the access key.)
		$preventAddressChanges = $reviewerAccessKeysEnabled;

		import('classes.mail.ArticleMailTemplate');

		$email = new ArticleMailTemplate($sectionEditorSubmission, $isEmailBasedReview?'REVIEW_REQUEST_ATTACHED':($reviewerAccessKeysEnabled?'REVIEW_REQUEST_ONECLICK':'REVIEW_REQUEST'), null, $isEmailBasedReview?true:null);

		if ($preventAddressChanges) {
			$email->setAddressFieldsEnabled(false);
		}

		if ($reviewAssignment->getSubmissionId() == $sectionEditorSubmission->getArticleId() && $reviewAssignment->getReviewFileId()) {
			$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());
			if (!isset($reviewer)) return true;

			if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
				HookRegistry::call('SectionEditorAction::notifyReviewer', array(&$sectionEditorSubmission, &$reviewAssignment, &$email));
				if ($email->isEnabled()) {
					$email->setAssoc(ARTICLE_EMAIL_REVIEW_NOTIFY_REVIEWER, ARTICLE_EMAIL_TYPE_REVIEW, $reviewId);
					if ($reviewerAccessKeysEnabled) {
						import('lib.pkp.classes.security.AccessKeyManager');
						import('pages.reviewer.ReviewerHandler');
						$accessKeyManager = new AccessKeyManager();

						// Key lifetime is the typical review period plus four weeks
						$keyLifetime = ($journal->getSetting('numWeeksPerReview') + 4) * 7;

						$email->addPrivateParam('ACCESS_KEY', $accessKeyManager->createKey('ReviewerContext', $reviewer->getId(), $reviewId, $keyLifetime));
					}

					if ($preventAddressChanges) {
						// Ensure that this messages goes to the reviewer, and the reviewer ONLY.
						$email->clearAllRecipients();
						$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
					}
					$email->send();
				}

				$reviewAssignment->setDateNotified(Core::getCurrentDate());
				$reviewAssignment->setCancelled(0);
				$reviewAssignment->stampModified();
				$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);
				return true;
			} else {
				if (!Request::getUserVar('continued') || $preventAddressChanges) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}

				if (!Request::getUserVar('continued')) {
					$weekLaterDate = strftime(Config::getVar('general', 'date_format_short'), strtotime('+1 week'));

					if ($reviewAssignment->getDateDue() != null) {
						$reviewDueDate = strftime(Config::getVar('general', 'date_format_short'), strtotime($reviewAssignment->getDateDue()));
					} else {
						$numWeeks = max((int) $journal->getSetting('numWeeksPerReview'), 2);
						$reviewDueDate = strftime(Config::getVar('general', 'date_format_short'), strtotime('+' . $numWeeks . ' week'));
					}

					$submissionUrl = Request::url(null, 'reviewer', 'submission', $reviewId, $reviewerAccessKeysEnabled?array('key' => 'ACCESS_KEY'):array());

					$paramArray = array(
						'reviewerName' => $reviewer->getFullName(),
						'weekLaterDate' => $weekLaterDate,
						'reviewDueDate' => $reviewDueDate,
						'reviewerUsername' => $reviewer->getUsername(),
						'reviewerPassword' => $reviewer->getPassword(),
						'editorialContactSignature' => $user->getContactSignature(),
						'reviewGuidelines' => String::html2text($journal->getLocalizedSetting('reviewGuidelines')),
						'submissionReviewUrl' => $submissionUrl,
						'abstractTermIfEnabled' => ($sectionEditorSubmission->getLocalizedAbstract() == ''?'':Locale::translate('article.abstract')),
						'passwordResetUrl' => Request::url(null, 'login', 'resetPassword', $reviewer->getUsername(), array('confirm' => Validation::generatePasswordResetHash($reviewer->getId())))
					);
					$email->assignParams($paramArray);
					if ($isEmailBasedReview) {
						// An email-based review process was selected. Attach
						// the current review version.
						import('classes.file.TemporaryFileManager');
						$temporaryFileManager = new TemporaryFileManager();
						$reviewVersion =& $sectionEditorSubmission->getReviewFile();
						if ($reviewVersion) {
							$temporaryFile = $temporaryFileManager->articleToTemporaryFile($reviewVersion, $user->getId());
							$email->addPersistAttachment($temporaryFile);
						}
					}
				}
				$email->displayEditForm(Request::url(null, null, 'notifyReviewer'), array('reviewId' => $reviewId, 'articleId' => $sectionEditorSubmission->getArticleId()));
				return false;
			}
		}
		return true;
	}

	/**
	 * Cancels a review.
	 * @param $sectionEditorSubmission object
	 * @param $reviewId int
	 * @return boolean true iff ready for redirect
	 */
	function cancelReview($sectionEditorSubmission, $reviewId, $send = false) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');

		$journal =& Request::getJournal();
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());
		if (!isset($reviewer)) return true;

		if ($reviewAssignment->getSubmissionId() == $sectionEditorSubmission->getArticleId()) {
			// Only cancel the review if it is currently not cancelled but has previously
			// been initiated, and has not been completed.
			if ($reviewAssignment->getDateNotified() != null && !$reviewAssignment->getCancelled() && ($reviewAssignment->getDateCompleted() == null || $reviewAssignment->getDeclined())) {
				import('classes.mail.ArticleMailTemplate');
				$email = new ArticleMailTemplate($sectionEditorSubmission, 'REVIEW_CANCEL');

				if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
					HookRegistry::call('SectionEditorAction::cancelReview', array(&$sectionEditorSubmission, &$reviewAssignment, &$email));
					if ($email->isEnabled()) {
						$email->setAssoc(ARTICLE_EMAIL_REVIEW_CANCEL, ARTICLE_EMAIL_TYPE_REVIEW, $reviewId);
						$email->send();
					}

					$reviewAssignment->setCancelled(1);
					$reviewAssignment->setDateCompleted(Core::getCurrentDate());
					$reviewAssignment->stampModified();

					$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);

					// Add log
					import('classes.article.log.ArticleLog');
					import('classes.article.log.ArticleEventLogEntry');
					ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_REVIEW_CANCEL, ARTICLE_LOG_TYPE_REVIEW, $reviewAssignment->getId(), 'log.review.reviewCancelled', array('reviewerName' => $reviewer->getFullName(), 'articleId' => $sectionEditorSubmission->getArticleId(), 'round' => $reviewAssignment->getRound()));
				} else {
					if (!Request::getUserVar('continued')) {
						$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());

						$paramArray = array(
							'reviewerName' => $reviewer->getFullName(),
							'reviewerUsername' => $reviewer->getUsername(),
							'reviewerPassword' => $reviewer->getPassword(),
							'editorialContactSignature' => $user->getContactSignature()
						);
						$email->assignParams($paramArray);
					}
					$email->displayEditForm(Request::url(null, null, 'cancelReview', 'send'), array('reviewId' => $reviewId, 'articleId' => $sectionEditorSubmission->getArticleId()));
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Reminds a reviewer about a review assignment.
	 * @param $sectionEditorSubmission object
	 * @param $reviewId int
	 * @return boolean true iff no error was encountered
	 */
	function remindReviewer($sectionEditorSubmission, $reviewId, $send = false) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');

		$journal =& Request::getJournal();
		$user =& Request::getUser();
		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$reviewerAccessKeysEnabled = $journal->getSetting('reviewerAccessKeysEnabled');

		// If we're using access keys, disable the address fields
		// for this message. (Prevents security issue: section editor
		// could CC or BCC someone else, or change the reviewer address,
		// in order to get the access key.)
		$preventAddressChanges = $reviewerAccessKeysEnabled;

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, $reviewerAccessKeysEnabled?'REVIEW_REMIND_ONECLICK':'REVIEW_REMIND');

		if ($preventAddressChanges) {
			$email->setAddressFieldsEnabled(false);
		}

		if ($send && !$email->hasErrors()) {
			HookRegistry::call('SectionEditorAction::remindReviewer', array(&$sectionEditorSubmission, &$reviewAssignment, &$email));
			$email->setAssoc(ARTICLE_EMAIL_REVIEW_REMIND, ARTICLE_EMAIL_TYPE_REVIEW, $reviewId);

			$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());

			if ($reviewerAccessKeysEnabled) {
				import('lib.pkp.classes.security.AccessKeyManager');
				import('pages.reviewer.ReviewerHandler');
				$accessKeyManager = new AccessKeyManager();

				// Key lifetime is the typical review period plus four weeks
				$keyLifetime = ($journal->getSetting('numWeeksPerReview') + 4) * 7;
				$email->addPrivateParam('ACCESS_KEY', $accessKeyManager->createKey('ReviewerContext', $reviewer->getId(), $reviewId, $keyLifetime));
			}

			if ($preventAddressChanges) {
				// Ensure that this messages goes to the reviewer, and the reviewer ONLY.
				$email->clearAllRecipients();
				$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
			}

			$email->send();

			$reviewAssignment->setDateReminded(Core::getCurrentDate());
			$reviewAssignment->setReminderWasAutomatic(0);
			$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);
			return true;
		} elseif ($reviewAssignment->getSubmissionId() == $sectionEditorSubmission->getArticleId()) {
			$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());

			if (!Request::getUserVar('continued')) {
				if (!isset($reviewer)) return true;
				$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());

				$submissionUrl = Request::url(null, 'reviewer', 'submission', $reviewId, $reviewerAccessKeysEnabled?array('key' => 'ACCESS_KEY'):array());

				// Format the review due date
				$reviewDueDate = strtotime($reviewAssignment->getDateDue());
				$dateFormatShort = Config::getVar('general', 'date_format_short');
				if ($reviewDueDate == -1) $reviewDueDate = $dateFormatShort; // Default to something human-readable if no date specified
				else $reviewDueDate = strftime($dateFormatShort, $reviewDueDate);

				$paramArray = array(
					'reviewerName' => $reviewer->getFullName(),
					'reviewerUsername' => $reviewer->getUsername(),
					'reviewerPassword' => $reviewer->getPassword(),
					'reviewDueDate' => $reviewDueDate,
					'editorialContactSignature' => $user->getContactSignature(),
					'passwordResetUrl' => Request::url(null, 'login', 'resetPassword', $reviewer->getUsername(), array('confirm' => Validation::generatePasswordResetHash($reviewer->getId()))),
					'submissionReviewUrl' => $submissionUrl
				);
				$email->assignParams($paramArray);

			}
			$email->displayEditForm(
			Request::url(null, null, 'remindReviewer', 'send'),
			array(
					'reviewerId' => $reviewer->getId(),
					'articleId' => $sectionEditorSubmission->getArticleId(),
					'reviewId' => $reviewId
			)
			);
			return false;
		}
		return true;
	}

	/**
	 * Thanks a reviewer for completing a review assignment.
	 * @param $sectionEditorSubmission object
	 * @param $reviewId int
	 * @return boolean true iff ready for redirect
	 */
	function thankReviewer($sectionEditorSubmission, $reviewId, $send = false) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');

		$journal =& Request::getJournal();
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, 'REVIEW_ACK');

		if ($reviewAssignment->getSubmissionId() == $sectionEditorSubmission->getArticleId()) {
			$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());
			if (!isset($reviewer)) return true;

			if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
				HookRegistry::call('SectionEditorAction::thankReviewer', array(&$sectionEditorSubmission, &$reviewAssignment, &$email));
				if ($email->isEnabled()) {
					$email->setAssoc(ARTICLE_EMAIL_REVIEW_THANK_REVIEWER, ARTICLE_EMAIL_TYPE_REVIEW, $reviewId);
					$email->send();
				}

				$reviewAssignment->setDateAcknowledged(Core::getCurrentDate());
				$reviewAssignment->stampModified();
				$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);
			} else {
				if (!Request::getUserVar('continued')) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());

					$paramArray = array(
						'reviewerName' => $reviewer->getFullName(),
						'editorialContactSignature' => $user->getContactSignature()
					);
					$email->assignParams($paramArray);
				}
				$email->displayEditForm(Request::url(null, null, 'thankReviewer', 'send'), array('reviewId' => $reviewId, 'articleId' => $sectionEditorSubmission->getArticleId()));
				return false;
			}
		}
		return true;
	}

	/**
	 * Rates a reviewer for quality of a review.
	 * @param $articleId int
	 * @param $reviewId int
	 * @param $quality int
	 */
	function rateReviewer($articleId, $reviewId, $quality = null) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());
		if (!isset($reviewer)) return false;

		if ($reviewAssignment->getSubmissionId() == $articleId && !HookRegistry::call('SectionEditorAction::rateReviewer', array(&$reviewAssignment, &$reviewer, &$quality))) {
			// Ensure that the value for quality
			// is between 1 and 5.
			if ($quality != null && ($quality >= 1 && $quality <= 5)) {
				$reviewAssignment->setQuality($quality);
			}

			$reviewAssignment->setDateRated(Core::getCurrentDate());
			$reviewAssignment->stampModified();

			$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			ArticleLog::logEvent($articleId, ARTICLE_LOG_REVIEW_RATE, ARTICLE_LOG_TYPE_REVIEW, $reviewAssignment->getId(), 'log.review.reviewerRated', array('reviewerName' => $reviewer->getFullName(), 'articleId' => $articleId, 'round' => $reviewAssignment->getRound()));
		}
	}

	/**
	 * Makes a reviewer's annotated version of an article available to the author.
	 * @param $articleId int
	 * @param $reviewId int
	 * @param $viewable boolean
	 */
	function makeReviewerFileViewable($articleId, $reviewId, $fileId, $revision, $viewable = false) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$articleFile =& $articleFileDao->getArticleFile($fileId, $revision);

		if ($reviewAssignment->getSubmissionId() == $articleId && $reviewAssignment->getReviewerFileId() == $fileId && !HookRegistry::call('SectionEditorAction::makeReviewerFileViewable', array(&$reviewAssignment, &$articleFile, &$viewable))) {
			$articleFile->setViewable($viewable);
			$articleFileDao->updateArticleFile($articleFile);
		}
	}

	/**
	 * Sets the due date for a review assignment.
	 * @param $articleId int
	 * @param $reviewId int
	 * @param $dueDate string
	 * @param $numWeeks int
	 * @param $logEntry boolean
	 */
	function setDueDate($articleId, $reviewId, $dueDate = null, $numWeeks = null, $logEntry = false) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());
		if (!isset($reviewer)) return false;

		if ($reviewAssignment->getSubmissionId() == $articleId && !HookRegistry::call('SectionEditorAction::setDueDate', array(&$reviewAssignment, &$reviewer, &$dueDate, &$numWeeks, &$meetingDate))) {
			$today = getDate();
			$todayTimestamp = mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']);
			if ($dueDate != null) {
				/*********************************************************
				 *
				 * Change format according to jquery date format
				 * Edited by aglet
				 * Last Update: 6/3/2011
				 *
				 *********************************************************/
				$dueDateParts = explode('-', $dueDate);

				// Ensure that the specified due date is today or after today's date.
				if ($todayTimestamp <= strtotime($dueDate)) {
					$reviewAssignment->setDateDue(mktime(0, 0, 0, $dueDateParts[1], $dueDateParts[2], $dueDateParts[0]));
				} else {
					$reviewAssignment->setDateDue(date('Y-m-d H:i:s', $todayTimestamp));
				}
			} else {
				// Add the equivalent of $numWeeks weeks, measured in seconds, to $todaysTimestamp.
				$newDueDateTimestamp = $todayTimestamp + ($numWeeks * 7 * 24 * 60 * 60);
				$reviewAssignment->setDateDue($newDueDateTimestamp);
			}
				
			$reviewAssignment->stampModified();
				
			$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);

			if ($logEntry) {
				// Add log
				import('classes.article.log.ArticleLog');
				import('classes.article.log.ArticleEventLogEntry');
				ArticleLog::logEvent(
				$articleId,
				ARTICLE_LOG_REVIEW_SET_DUE_DATE,
				ARTICLE_LOG_TYPE_REVIEW,
				$reviewAssignment->getId(),
					'log.review.reviewDueDateSet',
				array(
						'reviewerName' => $reviewer->getFullName(),
						'dueDate' => strftime(Config::getVar('general', 'date_format_short'),
				strtotime($reviewAssignment->getDateDue())),
						'articleId' => $articleId,
						'round' => $reviewAssignment->getRound()
				)
				);
			}
		}
	}

	/**
	 * Notifies an author that a submission was unsuitable.
	 * @param $sectionEditorSubmission object
	 * @return boolean true iff ready for redirect
	 */
	function unsuitableSubmission($sectionEditorSubmission, $send = false) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');

		$journal =& Request::getJournal();
		$user =& Request::getUser();

		$author =& $userDao->getUser($sectionEditorSubmission->getUserId());
		if (!isset($author)) return true;

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, 'SUBMISSION_UNSUITABLE');

		if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
			HookRegistry::call('SectionEditorAction::unsuitableSubmission', array(&$sectionEditorSubmission, &$author, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_EDITOR_NOTIFY_AUTHOR_UNSUITABLE, ARTICLE_EMAIL_TYPE_EDITOR, $user->getId());
				$email->send();
			}
			SectionEditorAction::archiveSubmission($sectionEditorSubmission);
			return true;
		} else {
			if (!Request::getUserVar('continued')) {
				$paramArray = array(
					'editorialContactSignature' => $user->getContactSignature(),
					'authorName' => $author->getFullName()
				);
				$email->assignParams($paramArray);
				$email->addRecipient($author->getEmail(), $author->getFullName());
			}
			$email->displayEditForm(Request::url(null, null, 'unsuitableSubmission'), array('articleId' => $sectionEditorSubmission->getArticleId()));
			return false;
		}
	}

	/**
	 * Sets the reviewer recommendation for a review assignment.
	 * Also concatenates the reviewer and editor comments from Peer Review and adds them to Editor Review.
	 * @param $articleId int
	 * @param $reviewId int
	 * @param $recommendation int
	 */
	function setReviewerRecommendation($articleId, $reviewId, $recommendation, $acceptOption) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId(), true);

		if ($reviewAssignment->getSubmissionId() == $articleId && !HookRegistry::call('SectionEditorAction::setReviewerRecommendation', array(&$reviewAssignment, &$reviewer, &$recommendation, &$acceptOption))) {
			$reviewAssignment->setRecommendation($recommendation);

			$nowDate = Core::getCurrentDate();
			if (!$reviewAssignment->getDateConfirmed()) {
				$reviewAssignment->setDateConfirmed($nowDate);
			}
			$reviewAssignment->setDateCompleted($nowDate);
			$reviewAssignment->stampModified();

			$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			ArticleLog::logEvent($articleId, ARTICLE_LOG_REVIEW_RECOMMENDATION_BY_PROXY, ARTICLE_LOG_TYPE_REVIEW, $reviewAssignment->getId(), 'log.review.reviewRecommendationSetByProxy', array('editorName' => $user->getFullName(), 'reviewerName' => $reviewer->getFullName(), 'articleId' => $articleId, 'round' => $reviewAssignment->getRound()));
		}
	}

	/**
	 * Clear a review form
	 * @param $sectionEditorSubmission object
	 * @param $reviewId int
	 */
	function clearReviewForm($sectionEditorSubmission, $reviewId) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);

		if (HookRegistry::call('SectionEditorAction::clearReviewForm', array(&$sectionEditorSubmission, &$reviewAssignment, &$reviewId))) return $reviewId;

		if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $sectionEditorSubmission->getArticleId()) {
			$reviewFormResponseDao =& DAORegistry::getDAO('ReviewFormResponseDAO');
			$responses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewId);
			if (!empty($responses)) {
				$reviewFormResponseDao->deleteByReviewId($reviewId);
			}
			$reviewAssignment->setReviewFormId(null);
			$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);
		}
	}

	/**
	 * Assigns a review form to a review.
	 * @param $sectionEditorSubmission object
	 * @param $reviewId int
	 * @param $reviewFormId int
	 */
	function addReviewForm($sectionEditorSubmission, $reviewId, $reviewFormId) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);

		if (HookRegistry::call('SectionEditorAction::addReviewForm', array(&$sectionEditorSubmission, &$reviewAssignment, &$reviewId, &$reviewFormId))) return $reviewFormId;

		if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $sectionEditorSubmission->getArticleId()) {
			// Only add the review form if it has not already
			// been assigned to the review.
			if ($reviewAssignment->getReviewFormId() != $reviewFormId) {
				$reviewFormResponseDao =& DAORegistry::getDAO('ReviewFormResponseDAO');
				$responses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewId);
				if (!empty($responses)) {
					$reviewFormResponseDao->deleteByReviewId($reviewId);
				}
				$reviewAssignment->setReviewFormId($reviewFormId);
				$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);
			}
		}
	}

	/**
	 * View review form response.
	 * @param $sectionEditorSubmission object
	 * @param $reviewId int
	 */
	function viewReviewFormResponse($sectionEditorSubmission, $reviewId) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);

		if (HookRegistry::call('SectionEditorAction::viewReviewFormResponse', array(&$sectionEditorSubmission, &$reviewAssignment, &$reviewId))) return $reviewId;

		if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $sectionEditorSubmission->getArticleId()) {
			$reviewFormId = $reviewAssignment->getReviewFormId();
			if ($reviewFormId != null) {
				import('classes.submission.form.ReviewFormResponseForm');
				$reviewForm = new ReviewFormResponseForm($reviewId, $reviewFormId);
				$reviewForm->initData();
				$reviewForm->display();
			}
		}
	}

	/**
	 * Set the file to use as the default copyedit file.
	 * @param $sectionEditorSubmission object
	 * @param $fileId int
	 * @param $revision int
	 * TODO: SECURITY!
	 */
	function setCopyeditFile($sectionEditorSubmission, $fileId, $revision) {
		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($sectionEditorSubmission->getArticleId());
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$user =& Request::getUser();

		if (!HookRegistry::call('SectionEditorAction::setCopyeditFile', array(&$sectionEditorSubmission, &$fileId, &$revision))) {
			// Copy the file from the editor decision file folder to the copyedit file folder
			$newFileId = $articleFileManager->copyToCopyeditFile($fileId, $revision);

			$copyeditSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());

			$copyeditSignoff->setFileId($newFileId);
			$copyeditSignoff->setFileRevision(1);

			$signoffDao->updateObject($copyeditSignoff);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_COPYEDIT_SET_FILE, ARTICLE_LOG_TYPE_COPYEDIT, $sectionEditorSubmission->getFileBySignoffType('SIGNOFF_COPYEDITING_INITIAL', true), 'log.copyedit.copyeditFileSet');
		}
	}

	function initiateNewReviewRound($sectionEditorSubmission) {
		if (!HookRegistry::call('SectionEditorAction::initiateNewReviewRound', array(&$sectionEditorSubmission))) {
			// Increment the round
			$currentRound = $sectionEditorSubmission->getCurrentRound();
			$sectionEditorSubmission->setCurrentRound($currentRound + 1);
			$sectionEditorSubmission->stampStatusModified();
			// Now, reassign all reviewers that submitted a review for this new round of reviews.
			$previousRound = $sectionEditorSubmission->getCurrentRound() - 1;
			foreach ($sectionEditorSubmission->getReviewAssignments($previousRound) as $reviewAssignment) {
				if ($reviewAssignment->getRecommendation() !== null && $reviewAssignment->getRecommendation() !== '') {
					// Then this reviewer submitted a review.
					SectionEditorAction::addReviewer($sectionEditorSubmission, $reviewAssignment->getReviewerId(), $sectionEditorSubmission->getCurrentRound());
				}
			}

		}
	}

	/**
	 * Resubmit the file for review.
	 * @param $sectionEditorSubmission object
	 * @param $fileId int
	 * @param $revision int
	 * TODO: SECURITY!
	 */
	function resubmitFile($sectionEditorSubmission, $fileId, $revision) {
		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($sectionEditorSubmission->getArticleId());
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
		$user =& Request::getUser();

		if (!HookRegistry::call('SectionEditorAction::resubmitFile', array(&$sectionEditorSubmission, &$fileId, &$revision))) {
			// Increment the round
			$currentRound = $sectionEditorSubmission->getCurrentRound();
			$sectionEditorSubmission->setCurrentRound($currentRound + 1);
			$sectionEditorSubmission->stampStatusModified();

			// Copy the file from the editor decision file folder to the review file folder
			$newFileId = $articleFileManager->copyToReviewFile($fileId, $revision, $sectionEditorSubmission->getReviewFileId());
			$newReviewFile = $articleFileDao->getArticleFile($newFileId);
			$newReviewFile->setRound($sectionEditorSubmission->getCurrentRound());
			$articleFileDao->updateArticleFile($newReviewFile);

			// Copy the file from the editor decision file folder to the next-round editor file
			// $editorFileId may or may not be null after assignment
			$editorFileId = $sectionEditorSubmission->getEditorFileId() != null ? $sectionEditorSubmission->getEditorFileId() : null;

			// $editorFileId definitely will not be null after assignment
			$editorFileId = $articleFileManager->copyToEditorFile($newFileId, null, $editorFileId);
			$newEditorFile = $articleFileDao->getArticleFile($editorFileId);
			$newEditorFile->setRound($sectionEditorSubmission->getCurrentRound());
			$articleFileDao->updateArticleFile($newEditorFile);

			// The review revision is the highest revision for the review file.
			$reviewRevision = $articleFileDao->getRevisionNumber($newFileId);
			$sectionEditorSubmission->setReviewRevision($reviewRevision);

			$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);

			// Now, reassign all reviewers that submitted a review for this new round of reviews.
			$previousRound = $sectionEditorSubmission->getCurrentRound() - 1;
			foreach ($sectionEditorSubmission->getReviewAssignments($previousRound) as $reviewAssignment) {
				if ($reviewAssignment->getRecommendation() !== null && $reviewAssignment->getRecommendation() !== '') {
					// Then this reviewer submitted a review.
					SectionEditorAction::addReviewer($sectionEditorSubmission, $reviewAssignment->getReviewerId(), $sectionEditorSubmission->getCurrentRound());
				}
			}


			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_REVIEW_RESUBMIT, ARTICLE_LOG_TYPE_EDITOR, $user->getId(), 'log.review.resubmit', array('articleId' => $sectionEditorSubmission->getArticleId()));
		}
	}

	/**
	 * Assigns a copyeditor to a submission.
	 * @param $sectionEditorSubmission object
	 * @param $copyeditorId int
	 */
	function selectCopyeditor($sectionEditorSubmission, $copyeditorId) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& Request::getUser();

		// Check to see if the requested copyeditor is not already
		// assigned to copyedit this article.
		$assigned = $sectionEditorSubmissionDao->copyeditorExists($sectionEditorSubmission->getArticleId(), $copyeditorId);

		// Only add the copyeditor if he has not already
		// been assigned to review this article.
		if (!$assigned && !HookRegistry::call('SectionEditorAction::selectCopyeditor', array(&$sectionEditorSubmission, &$copyeditorId))) {
			$copyeditInitialSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
			$copyeditInitialSignoff->setUserId($copyeditorId);
			$signoffDao->updateObject($copyeditInitialSignoff);

			$copyeditor =& $userDao->getUser($copyeditorId);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_COPYEDIT_ASSIGN, ARTICLE_LOG_TYPE_COPYEDIT, $copyeditorId, 'log.copyedit.copyeditorAssigned', array('copyeditorName' => $copyeditor->getFullName(), 'articleId' => $sectionEditorSubmission->getArticleId()));
		}
	}

	/**
	 * Notifies a copyeditor about a copyedit assignment.
	 * @param $sectionEditorSubmission object
	 * @return boolean true iff ready for redirect
	 */
	function notifyCopyeditor($sectionEditorSubmission, $send = false) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, 'COPYEDIT_REQUEST');

		$copyeditor = $sectionEditorSubmission->getUserBySignoffType('SIGNOFF_COPYEDITING_INITIAL');
		if (!isset($copyeditor)) return true;

		if ($sectionEditorSubmission->getFileBySignoffType('SIGNOFF_COPYEDITING_INITIAL') && (!$email->isEnabled() || ($send && !$email->hasErrors()))) {
			HookRegistry::call('SectionEditorAction::notifyCopyeditor', array(&$sectionEditorSubmission, &$copyeditor, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_COPYEDIT_NOTIFY_COPYEDITOR, ARTICLE_EMAIL_TYPE_COPYEDIT, $sectionEditorSubmission->getArticleId());
				$email->send();
			}

			$copyeditInitialSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
			$copyeditInitialSignoff->setDateNotified(Core::getCurrentDate());
			$copyeditInitialSignoff->setDateUnderway(null);
			$copyeditInitialSignoff->setDateCompleted(null);
			$copyeditInitialSignoff->setDateAcknowledged(null);
			$signoffDao->updateObject($copyeditInitialSignoff);
		} else {
			if (!Request::getUserVar('continued')) {
				$email->addRecipient($copyeditor->getEmail(), $copyeditor->getFullName());
				$paramArray = array(
					'copyeditorName' => $copyeditor->getFullName(),
					'copyeditorUsername' => $copyeditor->getUsername(),
					'copyeditorPassword' => $copyeditor->getPassword(),
					'editorialContactSignature' => $user->getContactSignature(),
					'submissionCopyeditingUrl' => Request::url(null, 'copyeditor', 'submission', $sectionEditorSubmission->getArticleId())
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'notifyCopyeditor', 'send'), array('articleId' => $sectionEditorSubmission->getArticleId()));
			return false;
		}
		return true;
	}

	/**
	 * Initiates the initial copyedit stage when the editor does the copyediting.
	 * @param $sectionEditorSubmission object
	 */
	function initiateCopyedit($sectionEditorSubmission) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$user =& Request::getUser();

		// Only allow copyediting to be initiated if a copyedit file exists.
		if ($sectionEditorSubmission->getFileBySignoffType('SIGNOFF_COPYEDITING_INITIAL') && !HookRegistry::call('SectionEditorAction::initiateCopyedit', array(&$sectionEditorSubmission))) {
			$signoffDao =& DAORegistry::getDAO('SignoffDAO');

			$copyeditSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
			if (!$copyeditSignoff->getUserId()) {
				$copyeditSignoff->setUserId($user->getId());
			}
			$copyeditSignoff->setDateNotified(Core::getCurrentDate());

			$signoffDao->updateObject($copyeditSignoff);
		}
	}

	/**
	 * Thanks a copyeditor about a copyedit assignment.
	 * @param $sectionEditorSubmission object
	 * @return boolean true iff ready for redirect
	 */
	function thankCopyeditor($sectionEditorSubmission, $send = false) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, 'COPYEDIT_ACK');

		$copyeditor = $sectionEditorSubmission->getUserBySignoffType('SIGNOFF_COPYEDITING_INITIAL');
		if (!isset($copyeditor)) return true;

		if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
			HookRegistry::call('SectionEditorAction::thankCopyeditor', array(&$sectionEditorSubmission, &$copyeditor, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_COPYEDIT_NOTIFY_ACKNOWLEDGE, ARTICLE_EMAIL_TYPE_COPYEDIT, $sectionEditorSubmission->getArticleId());
				$email->send();
			}

			$initialSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
			$initialSignoff->setDateAcknowledged(Core::getCurrentDate());
			$signoffDao->updateObject($initialSignoff);
		} else {
			if (!Request::getUserVar('continued')) {
				$email->addRecipient($copyeditor->getEmail(), $copyeditor->getFullName());
				$paramArray = array(
					'copyeditorName' => $copyeditor->getFullName(),
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'thankCopyeditor', 'send'), array('articleId' => $sectionEditorSubmission->getArticleId()));
			return false;
		}
		return true;
	}

	/**
	 * Notifies the author that the copyedit is complete.
	 * @param $sectionEditorSubmission object
	 * @return true iff ready for redirect
	 */
	function notifyAuthorCopyedit($sectionEditorSubmission, $send = false) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, 'COPYEDIT_AUTHOR_REQUEST');

		$author =& $userDao->getUser($sectionEditorSubmission->getUserId());
		if (!isset($author)) return true;

		if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
			HookRegistry::call('SectionEditorAction::notifyAuthorCopyedit', array(&$sectionEditorSubmission, &$author, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_COPYEDIT_NOTIFY_AUTHOR, ARTICLE_EMAIL_TYPE_COPYEDIT, $sectionEditorSubmission->getArticleId());
				$email->send();
			}

			$authorSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_AUTHOR', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
			$authorSignoff->setUserId($author->getId());
			$authorSignoff->setDateNotified(Core::getCurrentDate());
			$authorSignoff->setDateUnderway(null);
			$authorSignoff->setDateCompleted(null);
			$authorSignoff->setDateAcknowledged(null);
			$signoffDao->updateObject($authorSignoff);
		} else {
			if (!Request::getUserVar('continued')) {
				$email->addRecipient($author->getEmail(), $author->getFullName());
				$paramArray = array(
					'authorName' => $author->getFullName(),
					'authorUsername' => $author->getUsername(),
					'authorPassword' => $author->getPassword(),
					'editorialContactSignature' => $user->getContactSignature(),
					'submissionCopyeditingUrl' => Request::url(null, 'author', 'submission', $sectionEditorSubmission->getArticleId())

				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'notifyAuthorCopyedit', 'send'), array('articleId' => $sectionEditorSubmission->getArticleId()));
			return false;
		}
		return true;
	}

	/**
	 * Thanks an author for completing editor / author review.
	 * @param $sectionEditorSubmission object
	 * @return boolean true iff ready for redirect
	 */
	function thankAuthorCopyedit($sectionEditorSubmission, $send = false) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, 'COPYEDIT_AUTHOR_ACK');

		$author =& $userDao->getUser($sectionEditorSubmission->getUserId());
		if (!isset($author)) return true;

		if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
			HookRegistry::call('SectionEditorAction::thankAuthorCopyedit', array(&$sectionEditorSubmission, &$author, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_COPYEDIT_NOTIFY_AUTHOR_ACKNOWLEDGE, ARTICLE_EMAIL_TYPE_COPYEDIT, $sectionEditorSubmission->getArticleId());
				$email->send();
			}

			$signoff = $signoffDao->build('SIGNOFF_COPYEDITING_AUTHOR', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
			$signoff->setDateAcknowledged(Core::getCurrentDate());
			$signoffDao->updateObject($signoff);
		} else {
			if (!Request::getUserVar('continued')) {
				$email->addRecipient($author->getEmail(), $author->getFullName());
				$paramArray = array(
					'authorName' => $author->getFullName(),
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'thankAuthorCopyedit', 'send'), array('articleId' => $sectionEditorSubmission->getArticleId()));
			return false;
		}
		return true;
	}

	/**
	 * Notify copyeditor about final copyedit.
	 * @param $sectionEditorSubmission object
	 * @param $send boolean
	 * @return boolean true iff ready for redirect
	 */
	function notifyFinalCopyedit($sectionEditorSubmission, $send = false) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, 'COPYEDIT_FINAL_REQUEST');

		$copyeditor = $sectionEditorSubmission->getUserBySignoffType('SIGNOFF_COPYEDITING_INITIAL');
		if (!isset($copyeditor)) return true;

		if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
			HookRegistry::call('SectionEditorAction::notifyFinalCopyedit', array(&$sectionEditorSubmission, &$copyeditor, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_COPYEDIT_NOTIFY_FINAL, ARTICLE_EMAIL_TYPE_COPYEDIT, $sectionEditorSubmission->getArticleId());
				$email->send();
			}

			$signoff = $signoffDao->build('SIGNOFF_COPYEDITING_FINAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
			$signoff->setUserId($copyeditor->getId());
			$signoff->setDateNotified(Core::getCurrentDate());
			$signoff->setDateUnderway(null);
			$signoff->setDateCompleted(null);
			$signoff->setDateAcknowledged(null);

			$signoffDao->updateObject($signoff);
		} else {
			if (!Request::getUserVar('continued')) {
				$email->addRecipient($copyeditor->getEmail(), $copyeditor->getFullName());
				$paramArray = array(
					'copyeditorName' => $copyeditor->getFullName(),
					'copyeditorUsername' => $copyeditor->getUsername(),
					'copyeditorPassword' => $copyeditor->getPassword(),
					'editorialContactSignature' => $user->getContactSignature(),
					'submissionCopyeditingUrl' => Request::url(null, 'copyeditor', 'submission', $sectionEditorSubmission->getArticleId())
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'notifyFinalCopyedit', 'send'), array('articleId' => $sectionEditorSubmission->getArticleId()));
			return false;
		}
		return true;
	}

	/**
	 * Thank copyeditor for completing final copyedit.
	 * @param $sectionEditorSubmission object
	 * @return boolean true iff ready for redirect
	 */
	function thankFinalCopyedit($sectionEditorSubmission, $send = false) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($sectionEditorSubmission, 'COPYEDIT_FINAL_ACK');

		$copyeditor = $sectionEditorSubmission->getUserBySignoffType('SIGNOFF_COPYEDITING_INITIAL');
		if (!isset($copyeditor)) return true;

		if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
			HookRegistry::call('SectionEditorAction::thankFinalCopyedit', array(&$sectionEditorSubmission, &$copyeditor, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_COPYEDIT_NOTIFY_FINAL_ACKNOWLEDGE, ARTICLE_EMAIL_TYPE_COPYEDIT, $sectionEditorSubmission->getArticleId());
				$email->send();
			}

			$signoff = $signoffDao->build('SIGNOFF_COPYEDITING_FINAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
			$signoff->setDateAcknowledged(Core::getCurrentDate());
			$signoffDao->updateObject($signoff);
		} else {
			if (!Request::getUserVar('continued')) {
				$email->addRecipient($copyeditor->getEmail(), $copyeditor->getFullName());
				$paramArray = array(
					'copyeditorName' => $copyeditor->getFullName(),
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'thankFinalCopyedit', 'send'), array('articleId' => $sectionEditorSubmission->getArticleId()));
			return false;
		}
		return true;
	}

	/**
	 * Upload the review version of an article.
	 * @param $sectionEditorSubmission object
	 */
	function uploadReviewVersion($sectionEditorSubmission) {
		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($sectionEditorSubmission->getArticleId());
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');

		$fileName = 'upload';
		if ($articleFileManager->uploadedFileExists($fileName) && !HookRegistry::call('SectionEditorAction::uploadReviewVersion', array(&$sectionEditorSubmission))) {
			if ($sectionEditorSubmission->getReviewFileId() != null) {
				$reviewFileId = $articleFileManager->uploadReviewFile($fileName, $sectionEditorSubmission->getReviewFileId());
				// Increment the review revision.
				$sectionEditorSubmission->setReviewRevision($sectionEditorSubmission->getReviewRevision()+1);
			} else {
				$reviewFileId = $articleFileManager->uploadReviewFile($fileName);
				$sectionEditorSubmission->setReviewRevision(1);
			}
			$editorFileId = $articleFileManager->copyToEditorFile($reviewFileId, $sectionEditorSubmission->getReviewRevision(), $sectionEditorSubmission->getEditorFileId());
		}

		if (isset($reviewFileId) && $reviewFileId != 0 && isset($editorFileId) && $editorFileId != 0) {
			$sectionEditorSubmission->setReviewFileId($reviewFileId);
			$sectionEditorSubmission->setEditorFileId($editorFileId);

			$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);
		}
	}

	/**
	 * Upload the post-review version of an article.
	 * @param $sectionEditorSubmission object
	 */
	function uploadEditorVersion($sectionEditorSubmission) {
		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($sectionEditorSubmission->getArticleId());
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$user =& Request::getUser();

		$fileName = 'upload';
		if ($articleFileManager->uploadedFileExists($fileName) && !HookRegistry::call('SectionEditorAction::uploadEditorVersion', array(&$sectionEditorSubmission))) {
			if ($sectionEditorSubmission->getEditorFileId() != null) {
				$fileId = $articleFileManager->uploadEditorDecisionFile($fileName, $sectionEditorSubmission->getEditorFileId());
			} else {
				$fileId = $articleFileManager->uploadEditorDecisionFile($fileName);
			}
		}

		if (isset($fileId) && $fileId != 0) {
			$sectionEditorSubmission->setEditorFileId($fileId);

			$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');
			ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_EDITOR_FILE, ARTICLE_LOG_TYPE_EDITOR, $sectionEditorSubmission->getEditorFileId(), 'log.editor.editorFile');
		}
	}

	/**
	 * Upload the copyedit version of an article.
	 * @param $sectionEditorSubmission object
	 * @param $copyeditStage string
	 */
	function uploadCopyeditVersion($sectionEditorSubmission, $copyeditStage) {
		$articleId = $sectionEditorSubmission->getArticleId();
		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($articleId);
		$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');

		// Perform validity checks.
		$initialSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $articleId);
		$authorSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_AUTHOR', ASSOC_TYPE_ARTICLE, $articleId);

		if ($copyeditStage == 'final' && $authorSignoff->getDateCompleted() == null) return;
		if ($copyeditStage == 'author' && $initialSignoff->getDateCompleted() == null) return;

		$fileName = 'upload';
		if ($articleFileManager->uploadedFileExists($fileName) && !HookRegistry::call('SectionEditorAction::uploadCopyeditVersion', array(&$sectionEditorSubmission))) {
			if ($sectionEditorSubmission->getFileBySignoffType('SIGNOFF_COPYEDITING_INITIAL', true) != null) {
				$copyeditFileId = $articleFileManager->uploadCopyeditFile($fileName, $sectionEditorSubmission->getFileBySignoffType('SIGNOFF_COPYEDITING_INITIAL', true));
			} else {
				$copyeditFileId = $articleFileManager->uploadCopyeditFile($fileName);
			}
		}

		if (isset($copyeditFileId) && $copyeditFileId != 0) {
			if ($copyeditStage == 'initial') {
				$signoff =& $initialSignoff;
				$signoff->setFileId($copyeditFileId);
				$signoff->setFileRevision($articleFileDao->getRevisionNumber($copyeditFileId));
			} elseif ($copyeditStage == 'author') {
				$signoff =& $authorSignoff;
				$signoff->setFileId($copyeditFileId);
				$signoff->setFileRevision($articleFileDao->getRevisionNumber($copyeditFileId));
			} elseif ($copyeditStage == 'final') {
				$signoff = $signoffDao->build('SIGNOFF_COPYEDITING_FINAL', ASSOC_TYPE_ARTICLE, $articleId);
				$signoff->setFileId($copyeditFileId);
				$signoff->setFileRevision($articleFileDao->getRevisionNumber($copyeditFileId));
			}

			$signoffDao->updateObject($signoff);
		}
	}

	/**
	 * Editor completes initial copyedit (copyeditors disabled).
	 * @param $sectionEditorSubmission object
	 */
	function completeCopyedit($sectionEditorSubmission) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		// This is only allowed if copyeditors are disabled.
		if ($journal->getSetting('useCopyeditors')) return;

		if (HookRegistry::call('SectionEditorAction::completeCopyedit', array(&$sectionEditorSubmission))) return;

		$signoff = $signoffDao->build('SIGNOFF_COPYEDITING_INITIAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
		$signoff->setDateCompleted(Core::getCurrentDate());
		$signoffDao->updateObject($signoff);
		// Add log entry
		import('classes.article.log.ArticleLog');
		import('classes.article.log.ArticleEventLogEntry');
		ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_COPYEDIT_INITIAL, ARTICLE_LOG_TYPE_COPYEDIT, $user->getId(), 'log.copyedit.initialEditComplete', Array('copyeditorName' => $user->getFullName(), 'articleId' => $sectionEditorSubmission->getArticleId()));
	}

	/**
	 * Section editor completes final copyedit (copyeditors disabled).
	 * @param $sectionEditorSubmission object
	 */
	function completeFinalCopyedit($sectionEditorSubmission) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		// This is only allowed if copyeditors are disabled.
		if ($journal->getSetting('useCopyeditors')) return;

		if (HookRegistry::call('SectionEditorAction::completeFinalCopyedit', array(&$sectionEditorSubmission))) return;

		$copyeditSignoff = $signoffDao->build('SIGNOFF_COPYEDITING_FINAL', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());
		$copyeditSignoff->setDateCompleted(Core::getCurrentDate());
		$signoffDao->updateObject($copyeditSignoff);

		if ($copyEdFile = $sectionEditorSubmission->getFileBySignoffType('SIGNOFF_COPYEDITING_FINAL')) {
			// Set initial layout version to final copyedit version
			$layoutSignoff = $signoffDao->build('SIGNOFF_LAYOUT', ASSOC_TYPE_ARTICLE, $sectionEditorSubmission->getArticleId());

			if (!$layoutSignoff->getFileId()) {
				import('classes.file.ArticleFileManager');
				$articleFileManager = new ArticleFileManager($sectionEditorSubmission->getArticleId());
				if ($layoutFileId = $articleFileManager->copyToLayoutFile($copyEdFile->getFileId(), $copyEdFile->getRevision())) {
					$layoutSignoff->setFileId($layoutFileId);
					$signoffDao->updateObject($layoutSignoff);
				}
			}
		}
		// Add log entry
		import('classes.article.log.ArticleLog');
		import('classes.article.log.ArticleEventLogEntry');
		ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_COPYEDIT_FINAL, ARTICLE_LOG_TYPE_COPYEDIT, $user->getId(), 'log.copyedit.finalEditComplete', Array('copyeditorName' => $user->getFullName(), 'articleId' => $sectionEditorSubmission->getArticleId()));
	}

	/**
	 * Archive a submission.
	 * @param $sectionEditorSubmission object
	 */
	function archiveSubmission($sectionEditorSubmission) {
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$user =& Request::getUser();

		if (HookRegistry::call('SectionEditorAction::archiveSubmission', array(&$sectionEditorSubmission))) return;

		$sectionEditorSubmission->setStatus(STATUS_ARCHIVED);
		$sectionEditorSubmission->stampStatusModified();

		$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);

		// Add log
		import('classes.article.log.ArticleLog');
		import('classes.article.log.ArticleEventLogEntry');
		ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_EDITOR_ARCHIVE, ARTICLE_LOG_TYPE_EDITOR, $sectionEditorSubmission->getArticleId(), 'log.editor.archived', array('articleId' => $sectionEditorSubmission->getArticleId()));
	}

	/**
	 * Restores a submission to the queue.
	 * @param $sectionEditorSubmission object
	 */
	function restoreToQueue($sectionEditorSubmission) {
		if (HookRegistry::call('SectionEditorAction::restoreToQueue', array(&$sectionEditorSubmission))) return;

		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');

		// Determine which queue to return the article to: the
		// scheduling queue or the editing queue.
		$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
		$publishedArticle =& $publishedArticleDao->getPublishedArticleByArticleId($sectionEditorSubmission->getArticleId());
		if ($publishedArticle) {
			$sectionEditorSubmission->setStatus(STATUS_PUBLISHED);
		} else {
			$sectionEditorSubmission->setStatus(STATUS_QUEUED);
		}
		unset($publishedArticle);

		$sectionEditorSubmission->stampStatusModified();

		$sectionEditorSubmissionDao->updateSectionEditorSubmission($sectionEditorSubmission);

		// Add log
		import('classes.article.log.ArticleLog');
		import('classes.article.log.ArticleEventLogEntry');
		ArticleLog::logEvent($sectionEditorSubmission->getArticleId(), ARTICLE_LOG_EDITOR_RESTORE, ARTICLE_LOG_TYPE_EDITOR, $sectionEditorSubmission->getArticleId(), 'log.editor.restored', array('articleId' => $sectionEditorSubmission->getArticleId()));
	}

	/**
	 * Changes the section.
	 * @param $submission object
	 * @param $sectionId int
	 */
	function updateSection($submission, $sectionId) {
		if (HookRegistry::call('SectionEditorAction::updateSection', array(&$submission, &$sectionId))) return;

		$submissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$submission->setSectionId($sectionId); // FIXME validate this ID?
		$submissionDao->updateSectionEditorSubmission($submission);
	}

	/**
	 * Changes the submission RT comments status.
	 * @param $submission object
	 * @param $commentsStatus int
	 */
	function updateCommentsStatus($submission, $commentsStatus) {
		if (HookRegistry::call('SectionEditorAction::updateCommentsStatus', array(&$submission, &$commentsStatus))) return;

		$submissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$submission->setCommentsStatus($commentsStatus); // FIXME validate this?
		$submissionDao->updateSectionEditorSubmission($submission);
	}

	//
	// Layout Editing
	//

	/**
	 * Upload the layout version of an article.
	 * @param $submission object
	 */
	function uploadLayoutVersion($submission) {
		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($submission->getArticleId());
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');

		$layoutSignoff = $signoffDao->build('SIGNOFF_LAYOUT', ASSOC_TYPE_ARTICLE, $submission->getArticleId());

		$fileName = 'layoutFile';
		if ($articleFileManager->uploadedFileExists($fileName) && !HookRegistry::call('SectionEditorAction::uploadLayoutVersion', array(&$submission, &$layoutAssignment))) {
			if ($layoutSignoff->getFileId() != null) {
				$layoutFileId = $articleFileManager->uploadLayoutFile($fileName, $layoutSignoff->getFileId());
			} else {
				$layoutFileId = $articleFileManager->uploadLayoutFile($fileName);
			}
			$layoutSignoff->setFileId($layoutFileId);
			$signoffDao->updateObject($layoutSignoff);
		}
	}

	/**
	 * Assign a layout editor to a submission.
	 * @param $submission object
	 * @param $editorId int user ID of the new layout editor
	 */
	function assignLayoutEditor($submission, $editorId) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		if (HookRegistry::call('SectionEditorAction::assignLayoutEditor', array(&$submission, &$editorId))) return;

		import('classes.article.log.ArticleLog');
		import('classes.article.log.ArticleEventLogEntry');

		$layoutSignoff = $signoffDao->build('SIGNOFF_LAYOUT', ASSOC_TYPE_ARTICLE, $submission->getArticleId());
		$layoutProofSignoff = $signoffDao->build('SIGNOFF_PROOFREADING_LAYOUT', ASSOC_TYPE_ARTICLE, $submission->getArticleId());
		if ($layoutSignoff->getUserId()) {
			$layoutEditor =& $userDao->getUser($layoutSignoff->getUserId());
			ArticleLog::logEvent($submission->getArticleId(), ARTICLE_LOG_LAYOUT_UNASSIGN, ARTICLE_LOG_TYPE_LAYOUT, $layoutSignoff->getId(), 'log.layout.layoutEditorUnassigned', array('editorName' => $layoutEditor->getFullName(), 'articleId' => $submission->getArticleId()));
		}

		$layoutSignoff->setUserId($editorId);
		$layoutSignoff->setDateNotified(null);
		$layoutSignoff->setDateUnderway(null);
		$layoutSignoff->setDateCompleted(null);
		$layoutSignoff->setDateAcknowledged(null);
		$layoutProofSignoff->setUserId($editorId);
		$layoutProofSignoff->setDateNotified(null);
		$layoutProofSignoff->setDateUnderway(null);
		$layoutProofSignoff->setDateCompleted(null);
		$layoutProofSignoff->setDateAcknowledged(null);
		$signoffDao->updateObject($layoutSignoff);
		$signoffDao->updateObject($layoutProofSignoff);

		$layoutEditor =& $userDao->getUser($layoutSignoff->getUserId());
		ArticleLog::logEvent($submission->getArticleId(), ARTICLE_LOG_LAYOUT_ASSIGN, ARTICLE_LOG_TYPE_LAYOUT, $layoutSignoff->getId(), 'log.layout.layoutEditorAssigned', array('editorName' => $layoutEditor->getFullName(), 'articleId' => $submission->getArticleId()));
	}

	/**
	 * Notifies the current layout editor about an assignment.
	 * @param $submission object
	 * @param $send boolean
	 * @return boolean true iff ready for redirect
	 */
	function notifyLayoutEditor($submission, $send = false) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$submissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($submission, 'LAYOUT_REQUEST');
		$layoutSignoff = $signoffDao->getBySymbolic('SIGNOFF_LAYOUT', ASSOC_TYPE_ARTICLE, $submission->getArticleId());
		$layoutEditor =& $userDao->getUser($layoutSignoff->getUserId());
		if (!isset($layoutEditor)) return true;

		if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
			HookRegistry::call('SectionEditorAction::notifyLayoutEditor', array(&$submission, &$layoutEditor, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_LAYOUT_NOTIFY_EDITOR, ARTICLE_EMAIL_TYPE_LAYOUT, $layoutSignoff->getId());
				$email->send();
			}

			$layoutSignoff->setDateNotified(Core::getCurrentDate());
			$layoutSignoff->setDateUnderway(null);
			$layoutSignoff->setDateCompleted(null);
			$layoutSignoff->setDateAcknowledged(null);
			$signoffDao->updateObject($layoutSignoff);
		} else {
			if (!Request::getUserVar('continued')) {
				$email->addRecipient($layoutEditor->getEmail(), $layoutEditor->getFullName());
				$paramArray = array(
					'layoutEditorName' => $layoutEditor->getFullName(),
					'layoutEditorUsername' => $layoutEditor->getUsername(),
					'editorialContactSignature' => $user->getContactSignature(),
					'submissionLayoutUrl' => Request::url(null, 'layoutEditor', 'submission', $submission->getArticleId())
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'notifyLayoutEditor', 'send'), array('articleId' => $submission->getArticleId()));
			return false;
		}
		return true;
	}

	/**
	 * Sends acknowledgement email to the current layout editor.
	 * @param $submission object
	 * @param $send boolean
	 * @return boolean true iff ready for redirect
	 */
	function thankLayoutEditor($submission, $send = false) {
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$submissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();
		$user =& Request::getUser();

		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($submission, 'LAYOUT_ACK');

		$layoutSignoff = $signoffDao->getBySymbolic('SIGNOFF_LAYOUT', ASSOC_TYPE_ARTICLE, $submission->getArticleId());
		$layoutEditor =& $userDao->getUser($layoutSignoff->getUserId());
		if (!isset($layoutEditor)) return true;

		if (!$email->isEnabled() || ($send && !$email->hasErrors())) {
			HookRegistry::call('SectionEditorAction::thankLayoutEditor', array(&$submission, &$layoutEditor, &$email));
			if ($email->isEnabled()) {
				$email->setAssoc(ARTICLE_EMAIL_LAYOUT_THANK_EDITOR, ARTICLE_EMAIL_TYPE_LAYOUT, $layoutSignoff->getId());
				$email->send();
			}

			$layoutSignoff->setDateAcknowledged(Core::getCurrentDate());
			$signoffDao->updateObject($layoutSignoff);

		} else {
			if (!Request::getUserVar('continued')) {
				$email->addRecipient($layoutEditor->getEmail(), $layoutEditor->getFullName());
				$paramArray = array(
					'layoutEditorName' => $layoutEditor->getFullName(),
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'thankLayoutEditor', 'send'), array('articleId' => $submission->getArticleId()));
			return false;
		}
		return true;
	}

	/**
	 * Change the sequence order of a galley.
	 * @param $article object
	 * @param $galleyId int
	 * @param $direction char u = up, d = down
	 */
	function orderGalley($article, $galleyId, $direction) {
		import('classes.submission.layoutEditor.LayoutEditorAction');
		LayoutEditorAction::orderGalley($article, $galleyId, $direction);
	}

	/**
	 * Delete a galley.
	 * @param $article object
	 * @param $galleyId int
	 */
	function deleteGalley($article, $galleyId) {
		import('classes.submission.layoutEditor.LayoutEditorAction');
		LayoutEditorAction::deleteGalley($article, $galleyId);
	}

	/**
	 * Change the sequence order of a supplementary file.
	 * @param $article object
	 * @param $suppFileId int
	 * @param $direction char u = up, d = down
	 */
	function orderSuppFile($article, $suppFileId, $direction) {
		import('classes.submission.layoutEditor.LayoutEditorAction');
		LayoutEditorAction::orderSuppFile($article, $suppFileId, $direction);
	}

	/**
	 * Delete a supplementary file.
	 * @param $article object
	 * @param $suppFileId int
	 */
	function deleteSuppFile($article, $suppFileId) {
		import('classes.submission.layoutEditor.LayoutEditorAction');
		LayoutEditorAction::deleteSuppFile($article, $suppFileId);
	}

	/**
	 * Delete a file from an article.
	 * @param $submission object
	 * @param $fileId int
	 * @param $revision int (optional)
	 */
	function deleteArticleFile($submission, $fileId, $revision) {
		import('classes.file.ArticleFileManager');
		$file =& $submission->getEditorFile();

		if (isset($file) && $file->getFileId() == $fileId && !HookRegistry::call('SectionEditorAction::deleteArticleFile', array(&$submission, &$fileId, &$revision))) {
			$articleFileManager = new ArticleFileManager($submission->getArticleId());
			$articleFileManager->deleteFile($fileId, $revision);
		}
	}

	/**
	 * Delete an image from an article galley.
	 * @param $submission object
	 * @param $fileId int
	 * @param $revision int (optional)
	 */
	function deleteArticleImage($submission, $fileId, $revision) {
		import('classes.file.ArticleFileManager');
		$articleGalleyDao =& DAORegistry::getDAO('ArticleGalleyDAO');
		if (HookRegistry::call('SectionEditorAction::deleteArticleImage', array(&$submission, &$fileId, &$revision))) return;
		foreach ($submission->getGalleys() as $galley) {
			$images =& $articleGalleyDao->getGalleyImages($galley->getId());
			foreach ($images as $imageFile) {
				if ($imageFile->getArticleId() == $submission->getArticleId() && $fileId == $imageFile->getFileId() && $imageFile->getRevision() == $revision) {
					$articleFileManager = new ArticleFileManager($submission->getArticleId());
					$articleFileManager->deleteFile($imageFile->getFileId(), $imageFile->getRevision());
				}
			}
			unset($images);
		}
	}

	/**
	 * Add Submission Note
	 * @param $articleId int
	 */
	function addSubmissionNote($articleId) {
		import('classes.file.ArticleFileManager');

		$noteDao =& DAORegistry::getDAO('NoteDAO');
		$user =& Request::getUser();
		$journal =& Request::getJournal();

		$note = $noteDao->newDataObject();
		$note->setAssocType(ASSOC_TYPE_ARTICLE);
		$note->setAssocId($articleId);
		$note->setUserId($user->getId());
		$note->setContextId($journal->getId());
		$note->setDateCreated(Core::getCurrentDate());
		$note->setDateModified(Core::getCurrentDate());
		$note->setTitle(Request::getUserVar('title'));
		$note->setContents(Request::getUserVar('note'));

		if (!HookRegistry::call('SectionEditorAction::addSubmissionNote', array(&$articleId, &$note))) {
			$articleFileManager = new ArticleFileManager($articleId);
			if ($articleFileManager->uploadedFileExists('upload')) {
				$fileId = $articleFileManager->uploadSubmissionNoteFile('upload');
			} else {
				$fileId = 0;
			}

			$note->setFileId($fileId);

			$noteDao->insertObject($note);
		}
	}

	/**
	 * Remove Submission Note
	 * @param $articleId int
	 */
	function removeSubmissionNote($articleId) {
		$noteId = Request::getUserVar('noteId');
		$fileId = Request::getUserVar('fileId');

		if (HookRegistry::call('SectionEditorAction::removeSubmissionNote', array(&$articleId, &$noteId, &$fileId))) return;

		// if there is an attached file, remove it as well
		if ($fileId) {
			import('classes.file.ArticleFileManager');
			$articleFileManager = new ArticleFileManager($articleId);
			$articleFileManager->deleteFile($fileId);
		}

		$noteDao =& DAORegistry::getDAO('NoteDAO');
		$noteDao->deleteById($noteId);
	}

	/**
	 * Updates Submission Note
	 * @param $articleId int
	 */
	function updateSubmissionNote($articleId) {
		import('classes.file.ArticleFileManager');

		$noteDao =& DAORegistry::getDAO('NoteDAO');

		$user =& Request::getUser();
		$journal =& Request::getJournal();

		$note = new Note();
		$note->setId(Request::getUserVar('noteId'));
		$note->setAssocType(ASSOC_TYPE_ARTICLE);
		$note->setAssocId($articleId);
		$note->setUserId($user->getId());
		$note->setDateModified(Core::getCurrentDate());
		$note->setContextId($journal->getId());
		$note->setTitle(Request::getUserVar('title'));
		$note->setContents(Request::getUserVar('note'));
		$note->setFileId(Request::getUserVar('fileId'));

		if (HookRegistry::call('SectionEditorAction::updateSubmissionNote', array(&$articleId, &$note))) return;

		$articleFileManager = new ArticleFileManager($articleId);

		// if there is a new file being uploaded
		if ($articleFileManager->uploadedFileExists('upload')) {
			// Attach the new file to the note, overwriting existing file if necessary
			$fileId = $articleFileManager->uploadSubmissionNoteFile('upload', $note->getFileId(), true);
			$note->setFileId($fileId);

		} else {
			if (Request::getUserVar('removeUploadedFile')) {
				$articleFileManager = new ArticleFileManager($articleId);
				$articleFileManager->deleteFile($note->getFileId());
				$note->setFileId(0);
			}
		}

		$noteDao->updateObject($note);
	}

	/**
	 * Clear All Submission Notes
	 * @param $articleId int
	 */
	function clearAllSubmissionNotes($articleId) {
		if (HookRegistry::call('SectionEditorAction::clearAllSubmissionNotes', array(&$articleId))) return;

		import('classes.file.ArticleFileManager');

		$noteDao =& DAORegistry::getDAO('NoteDAO');

		$fileIds = $noteDao->getAllFileIds(ASSOC_TYPE_ARTICLE, $articleId);

		if (!empty($fileIds)) {
			$articleFileDao =& DAORegistry::getDAO('ArticleFileDAO');
			$articleFileManager = new ArticleFileManager($articleId);

			foreach ($fileIds as $fileId) {
				$articleFileManager->deleteFile($fileId);
			}
		}

		$noteDao->deleteByAssoc(ASSOC_TYPE_ARTICLE, $articleId);

	}

	//
	// Comments
	//

	/**
	 * View reviewer comments.
	 * @param $article object
	 * @param $reviewId int
	 */
	function viewPeerReviewComments(&$article, $reviewId) {
		if (HookRegistry::call('SectionEditorAction::viewPeerReviewComments', array(&$article, &$reviewId))) return;

		import('classes.submission.form.comment.PeerReviewCommentForm');

		$commentForm = new PeerReviewCommentForm($article, $reviewId, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->initData();
		$commentForm->display();
	}

	/**
	 * Post reviewer comments.
	 * @param $article object
	 * @param $reviewId int
	 * @param $emailComment boolean
	 */
	function postPeerReviewComment(&$article, $reviewId, $emailComment) {
		if (HookRegistry::call('SectionEditorAction::postPeerReviewComment', array(&$article, &$reviewId, &$emailComment))) return;

		import('classes.submission.form.comment.PeerReviewCommentForm');

		$commentForm = new PeerReviewCommentForm($article, $reviewId, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->readInputData();

		if ($commentForm->validate()) {
			$commentForm->execute();

                        //Added by AIM, 02.17.2012
                        $roleDao =& DAORegistry::getDAO('RoleDAO');

			// Send a notification to associated users
			import('lib.pkp.classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
			$notificationUsers = $article->getAssociatedUserIds();
                        foreach ($notificationUsers as $userRole) {
                                if($userRole['role'] == $roleDao->getRolePath(ROLE_ID_EDITOR) || $userRole['role'] == $roleDao->getRolePath(ROLE_ID_SECTION_EDITOR)) { //If condition added by AIM, , send notification to Secretary only
                                    $url = Request::url(null, $userRole['role'], 'submissionReview', $article->getId(), null, 'peerReview');
                                    $notificationManager->createNotification(
                                    $userRole['id'], 'notification.type.reviewerComment',
                                    $article->getLocalizedTitle(), $url, 1, NOTIFICATION_TYPE_REVIEWER_COMMENT
                                    );
                                }
			}

			if ($emailComment) {
				$commentForm->email();
			}

		} else {
			$commentForm->display();
			return false;
		}
		return true;
	}

	/**
	 * View editor decision comments.
	 * @param $article object
	 */
	function viewEditorDecisionComments($article) {
		if (HookRegistry::call('SectionEditorAction::viewEditorDecisionComments', array(&$article))) return;

		import('classes.submission.form.comment.EditorDecisionCommentForm');

		$commentForm = new EditorDecisionCommentForm($article, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->initData();
		$commentForm->display();
	}

	/**
	 * Post editor decision comment.
	 * @param $article int
	 * @param $emailComment boolean
	 */
	function postEditorDecisionComment($article, $emailComment) {
		if (HookRegistry::call('SectionEditorAction::postEditorDecisionComment', array(&$article, &$emailComment))) return;

		import('classes.submission.form.comment.EditorDecisionCommentForm');

		$commentForm = new EditorDecisionCommentForm($article, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->readInputData();

		if ($commentForm->validate()) {
			$commentForm->execute();

			// Send a notification to associated users
			import('lib.pkp.classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
			$notificationUsers = $article->getAssociatedUserIds(true, false);
			foreach ($notificationUsers as $userRole) {
				$url = Request::url(null, $userRole['role'], 'submissionReview', $article->getId(), null, 'editorDecision');
				$notificationManager->createNotification(
				$userRole['id'], 'notification.type.editorDecisionComment',
				$article->getLocalizedTitle(), $url, 1, NOTIFICATION_TYPE_EDITOR_DECISION_COMMENT
				);
			}

			if ($emailComment) {
				$commentForm->email();
			}
		} else {
			$commentForm->display();
			return false;
		}
		return true;
	}

	/**
	 * Email editor decision comment
	 * @param $sectionEditorSubmission object
	 * @param $send boolean
	 */
	function emailEditorDecisionComment($sectionEditorSubmission, $send) {
		$userDao =& DAORegistry::getDAO('UserDAO');
		$articleCommentDao =& DAORegistry::getDAO('ArticleCommentDAO');
		$sectionEditorSubmissionDao =& DAORegistry::getDAO('SectionEditorSubmissionDAO');

		$journal =& Request::getJournal();

		$user =& Request::getUser();
		import('classes.mail.ArticleMailTemplate');

		$decisionTemplateMap = array(
		SUBMISSION_EDITOR_DECISION_ACCEPT => 'EDITOR_DECISION_ACCEPT',
		SUBMISSION_EDITOR_DECISION_RESUBMIT => 'EDITOR_DECISION_RESUBMIT',
		SUBMISSION_EDITOR_DECISION_DECLINE => 'EDITOR_DECISION_DECLINE'
		);

		$decisions = $sectionEditorSubmission->getDecisions();
		$decisions = array_pop($decisions); // Rounds
		$decision = array_pop($decisions);
		$decisionConst = $decision?$decision['decision']:null;

		$email = new ArticleMailTemplate(
		$sectionEditorSubmission,
		isset($decisionTemplateMap[$decisionConst])?$decisionTemplateMap[$decisionConst]:null
		);

		$copyeditor = $sectionEditorSubmission->getUserBySignoffType('SIGNOFF_COPYEDITING_INITIAL');

		if ($send && !$email->hasErrors()) {
			HookRegistry::call('SectionEditorAction::emailEditorDecisionComment', array(&$sectionEditorSubmission, &$send));
			$email->send();

			$articleComment = new ArticleComment();
			$articleComment->setCommentType(COMMENT_TYPE_EDITOR_DECISION);
			$articleComment->setRoleId(Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
			$articleComment->setArticleId($sectionEditorSubmission->getArticleId());
			$articleComment->setAuthorId($sectionEditorSubmission->getUserId());
			$articleComment->setCommentTitle($email->getSubject());
			$articleComment->setComments($email->getBody());
			$articleComment->setDatePosted(Core::getCurrentDate());
			$articleComment->setViewable(true);
			$articleComment->setAssocId($sectionEditorSubmission->getArticleId());
			$articleCommentDao->insertArticleComment($articleComment);

			return true;
		} else {
			if (!Request::getUserVar('continued')) {
				$authorUser =& $userDao->getUser($sectionEditorSubmission->getUserId());
				$authorEmail = $authorUser->getEmail();
				$email->assignParams(array(
					'editorialContactSignature' => $user->getContactSignature(),
					'authorName' => $authorUser->getFullName(),
					'journalTitle' => $journal->getLocalizedTitle()
				));
				$email->addRecipient($authorEmail, $authorUser->getFullName());
				if ($journal->getSetting('notifyAllAuthorsOnDecision')) foreach ($sectionEditorSubmission->getAuthors() as $author) {
					if ($author->getEmail() != $authorEmail) {
						$email->addCc ($author->getEmail(), $author->getFullName());
					}
				}
			} elseif (Request::getUserVar('importPeerReviews')) {
				$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
				$reviewAssignments =& $reviewAssignmentDao->getBySubmissionId($sectionEditorSubmission->getArticleId(), $sectionEditorSubmission->getCurrentRound());
				$reviewIndexes =& $reviewAssignmentDao->getReviewIndexesForRound($sectionEditorSubmission->getArticleId(), $sectionEditorSubmission->getCurrentRound());

				$body = '';
				foreach ($reviewAssignments as $reviewAssignment) {
					// If the reviewer has completed the assignment, then import the review.
					if ($reviewAssignment->getDateCompleted() != null && !$reviewAssignment->getCancelled()) {
						// Get the comments associated with this review assignment
						$articleComments =& $articleCommentDao->getArticleComments($sectionEditorSubmission->getArticleId(), COMMENT_TYPE_PEER_REVIEW, $reviewAssignment->getId());
						if($articleComments) {
							$body .= "------------------------------------------------------\n";
							$body .= Locale::translate('submission.comments.importPeerReviews.reviewerLetter', array('reviewerLetter' => chr(ord('A') + $reviewIndexes[$reviewAssignment->getId()]))) . "\n";
							if (is_array($articleComments)) {
								foreach ($articleComments as $comment) {
									// If the comment is viewable by the author, then add the comment.
									if ($comment->getViewable()) $body .= String::html2text($comment->getComments()) . "\n\n";
								}
							}
							$body .= "------------------------------------------------------\n\n";
						}
						if ($reviewFormId = $reviewAssignment->getReviewFormId()) {
							$reviewId = $reviewAssignment->getId();
							$reviewFormResponseDao =& DAORegistry::getDAO('ReviewFormResponseDAO');
							$reviewFormElementDao =& DAORegistry::getDAO('ReviewFormElementDAO');
							$reviewFormElements =& $reviewFormElementDao->getReviewFormElements($reviewFormId);
							if(!$articleComments) {
								$body .= "------------------------------------------------------\n";
								$body .= Locale::translate('submission.comments.importPeerReviews.reviewerLetter', array('reviewerLetter' => chr(ord('A') + $reviewIndexes[$reviewAssignment->getId()]))) . "\n\n";
							}
							foreach ($reviewFormElements as $reviewFormElement) if ($reviewFormElement->getIncluded()) {
								$body .= String::html2text($reviewFormElement->getLocalizedQuestion()) . ": \n";
								$reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewId, $reviewFormElement->getId());

								if ($reviewFormResponse) {
									$possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
									if (in_array($reviewFormElement->getElementType(), $reviewFormElement->getMultipleResponsesElementTypes())) {
										if ($reviewFormElement->getElementType() == REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
											foreach ($reviewFormResponse->getValue() as $value) {
												$body .= "\t" . String::html2text($possibleResponses[$value-1]['content']) . "\n";
											}
										} else {
											$body .= "\t" . String::html2text($possibleResponses[$reviewFormResponse->getValue()-1]['content']) . "\n";
										}
										$body .= "\n";
									} else {
										$body .= "\t" . String::html2text($reviewFormResponse->getValue()) . "\n\n";
									}
								}
							}
							$body .= "------------------------------------------------------\n\n";
						}
					}
				}
				$oldBody = $email->getBody();
				if (!empty($oldBody)) $oldBody .= "\n";
				$email->setBody($oldBody . $body);
			}

			$email->displayEditForm(Request::url(null, null, 'emailEditorDecisionComment', 'send'), array('articleId' => $sectionEditorSubmission->getArticleId()), 'submission/comment/editorDecisionEmail.tpl', array('isAnEditor' => true));

			return false;
		}
	}


	/**
	 * Blind CC the reviews to reviewers.
	 * @param $article object
	 * @param $send boolean
	 * @param $inhibitExistingEmail boolean
	 * @return boolean true iff ready for redirect
	 */
	function blindCcReviewsToReviewers($article, $send = false, $inhibitExistingEmail = false) {
		$commentDao =& DAORegistry::getDAO('ArticleCommentDAO');
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$journal =& Request::getJournal();

		$comments =& $commentDao->getArticleComments($article->getId(), COMMENT_TYPE_EDITOR_DECISION);
		$reviewAssignments =& $reviewAssignmentDao->getBySubmissionId($article->getId(), $article->getCurrentRound());

		$commentsText = "";
		foreach ($comments as $comment) {
			$commentsText .= String::html2text($comment->getComments()) . "\n\n";
		}

		$user =& Request::getUser();
		import('classes.mail.ArticleMailTemplate');
		$email = new ArticleMailTemplate($article, 'SUBMISSION_DECISION_REVIEWERS');

		if ($send && !$email->hasErrors() && !$inhibitExistingEmail) {
			HookRegistry::call('SectionEditorAction::blindCcReviewsToReviewers', array(&$article, &$reviewAssignments, &$email));
			$email->send();
			return true;
		} else {
			if ($inhibitExistingEmail || !Request::getUserVar('continued')) {
				$email->clearRecipients();
				foreach ($reviewAssignments as $reviewAssignment) {
					if ($reviewAssignment->getDateCompleted() != null && !$reviewAssignment->getCancelled()) {
						$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId());

						if (isset($reviewer)) $email->addBcc($reviewer->getEmail(), $reviewer->getFullName());
					}
				}

				$paramArray = array(
					'comments' => $commentsText,
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}

			$email->displayEditForm(Request::url(null, null, 'blindCcReviewsToReviewers'), array('articleId' => $article->getId()));
			return false;
		}
	}

	/**
	 * View copyedit comments.
	 * @param $article object
	 */
	function viewCopyeditComments($article) {
		if (HookRegistry::call('SectionEditorAction::viewCopyeditComments', array(&$article))) return;

		import('classes.submission.form.comment.CopyeditCommentForm');

		$commentForm = new CopyeditCommentForm($article, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->initData();
		$commentForm->display();
	}

	/**
	 * Post copyedit comment.
	 * @param $article object
	 * @param $emailComment boolean
	 */
	function postCopyeditComment($article, $emailComment) {
		if (HookRegistry::call('SectionEditorAction::postCopyeditComment', array(&$article, &$emailComment))) return;

		import('classes.submission.form.comment.CopyeditCommentForm');

		$commentForm = new CopyeditCommentForm($article, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->readInputData();

		if ($commentForm->validate()) {
			$commentForm->execute();

			// Send a notification to associated users
			import('lib.pkp.classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
			$notificationUsers = $article->getAssociatedUserIds(true, false);
			foreach ($notificationUsers as $userRole) {
				$url = Request::url(null, $userRole['role'], 'submissionEditing', $article->getId(), null, 'copyedit');
				$notificationManager->createNotification(
				$userRole['id'], 'notification.type.copyeditComment',
				$article->getLocalizedTitle(), $url, 1, NOTIFICATION_TYPE_COPYEDIT_COMMENT
				);
			}

			if ($emailComment) {
				$commentForm->email();
			}

		} else {
			$commentForm->display();
			return false;
		}
		return true;
	}

	/**
	 * View layout comments.
	 * @param $article object
	 */
	function viewLayoutComments($article) {
		if (HookRegistry::call('SectionEditorAction::viewLayoutComments', array(&$article))) return;

		import('classes.submission.form.comment.LayoutCommentForm');

		$commentForm = new LayoutCommentForm($article, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->initData();
		$commentForm->display();
	}

	/**
	 * Post layout comment.
	 * @param $article object
	 * @param $emailComment boolean
	 */
	function postLayoutComment($article, $emailComment) {
		if (HookRegistry::call('SectionEditorAction::postLayoutComment', array(&$article, &$emailComment))) return;

		import('classes.submission.form.comment.LayoutCommentForm');

		$commentForm = new LayoutCommentForm($article, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->readInputData();

		if ($commentForm->validate()) {
			$commentForm->execute();

			// Send a notification to associated users
			import('lib.pkp.classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
			$notificationUsers = $article->getAssociatedUserIds(true, false);
			foreach ($notificationUsers as $userRole) {
				$url = Request::url(null, $userRole['role'], 'submissionEditing', $article->getId(), null, 'layout');
				$notificationManager->createNotification(
				$userRole['id'], 'notification.type.layoutComment',
				$article->getLocalizedTitle(), $url, 1, NOTIFICATION_TYPE_LAYOUT_COMMENT
				);
			}

			if ($emailComment) {
				$commentForm->email();
			}

		} else {
			$commentForm->display();
			return false;
		}
		return true;
	}

	/**
	 * View proofread comments.
	 * @param $article object
	 */
	function viewProofreadComments($article) {
		if (HookRegistry::call('SectionEditorAction::viewProofreadComments', array(&$article))) return;

		import('classes.submission.form.comment.ProofreadCommentForm');

		$commentForm = new ProofreadCommentForm($article, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->initData();
		$commentForm->display();
	}

	/**
	 * Post proofread comment.
	 * @param $article object
	 * @param $emailComment boolean
	 */
	function postProofreadComment($article, $emailComment) {
		if (HookRegistry::call('SectionEditorAction::postProofreadComment', array(&$article, &$emailComment))) return;

		import('classes.submission.form.comment.ProofreadCommentForm');

		$commentForm = new ProofreadCommentForm($article, Validation::isEditor()?ROLE_ID_EDITOR:ROLE_ID_SECTION_EDITOR);
		$commentForm->readInputData();

		if ($commentForm->validate()) {
			$commentForm->execute();

			// Send a notification to associated users
			import('lib.pkp.classes.notification.NotificationManager');
			$notificationManager = new NotificationManager();
			$notificationUsers = $article->getAssociatedUserIds(true, false);
			foreach ($notificationUsers as $userRole) {
				$url = Request::url(null, $userRole['role'], 'submissionEditing', $article->getId(), null, 'proofread');
				$notificationManager->createNotification(
				$userRole['id'], 'notification.type.proofreadComment',
				$article->getLocalizedTitle(), $url, 1, NOTIFICATION_TYPE_PROOFREAD_COMMENT
				);
			}

			if ($emailComment) {
				$commentForm->email();
			}

		} else {
			$commentForm->display();
			return false;
		}
		return true;
	}

	/**
	 * Confirms the review assignment on behalf of its reviewer.
	 * @param $reviewId int
	 * @param $accept boolean True === accept; false === decline
	 */
	function confirmReviewForReviewer($reviewId, $accept) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId(), true);

		if (HookRegistry::call('SectionEditorAction::acceptReviewForReviewer', array(&$reviewAssignment, &$reviewer, &$accept))) return;

		// Only confirm the review for the reviewer if
		// he has not previously done so.
		if ($reviewAssignment->getDateConfirmed() == null) {
			$reviewAssignment->setDeclined($accept?0:1);
			$reviewAssignment->setDateConfirmed(Core::getCurrentDate());
			$reviewAssignment->stampModified();
			$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');

			$entry = new ArticleEventLogEntry();
			$entry->setArticleId($reviewAssignment->getSubmissionId());
			$entry->setUserId($user->getId());
			$entry->setDateLogged(Core::getCurrentDate());
			$entry->setEventType(ARTICLE_LOG_REVIEW_CONFIRM_BY_PROXY);
			$entry->setLogMessage($accept?'log.review.reviewAcceptedByProxy':'log.review.reviewDeclinedByProxy', array('reviewerName' => $reviewer->getFullName(), 'articleId' => $reviewAssignment->getSubmissionId(), 'round' => $reviewAssignment->getRound(), 'userName' => $user->getFullName()));
			$entry->setAssocType(ARTICLE_LOG_TYPE_REVIEW);
			$entry->setAssocId($reviewAssignment->getId());

			ArticleLog::logEventEntry($reviewAssignment->getSubmissionId(), $entry);
		}
	}

	/**
	 * Upload a review on behalf of its reviewer.
	 * @param $reviewId int
	 */
	function uploadReviewForReviewer($reviewId) {
		$reviewAssignmentDao =& DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& Request::getUser();

		$reviewAssignment =& $reviewAssignmentDao->getById($reviewId);
		$reviewer =& $userDao->getUser($reviewAssignment->getReviewerId(), true);

		if (HookRegistry::call('SectionEditorAction::uploadReviewForReviewer', array(&$reviewAssignment, &$reviewer))) return;

		// Upload the review file.
		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($reviewAssignment->getSubmissionId());
		// Only upload the file if the reviewer has yet to submit a recommendation
		if (($reviewAssignment->getRecommendation() === null || $reviewAssignment->getRecommendation() === '') && !$reviewAssignment->getCancelled()) {
			$fileName = 'upload';
			if ($articleFileManager->uploadedFileExists($fileName)) {
				if ($reviewAssignment->getReviewerFileId() != null) {
					$fileId = $articleFileManager->uploadReviewFile($fileName, $reviewAssignment->getReviewerFileId());
				} else {
					$fileId = $articleFileManager->uploadReviewFile($fileName);
				}
			}
		}

		if (isset($fileId) && $fileId != 0) {
			// Only confirm the review for the reviewer if
			// he has not previously done so.
			if ($reviewAssignment->getDateConfirmed() == null) {
				$reviewAssignment->setDeclined(0);
				$reviewAssignment->setDateConfirmed(Core::getCurrentDate());
			}

			$reviewAssignment->setReviewerFileId($fileId);
			$reviewAssignment->stampModified();
			$reviewAssignmentDao->updateReviewAssignment($reviewAssignment);

			// Add log
			import('classes.article.log.ArticleLog');
			import('classes.article.log.ArticleEventLogEntry');

			$entry = new ArticleEventLogEntry();
			$entry->setArticleId($reviewAssignment->getSubmissionId());
			$entry->setUserId($user->getId());
			$entry->setDateLogged(Core::getCurrentDate());
			$entry->setEventType(ARTICLE_LOG_REVIEW_FILE_BY_PROXY);
			$entry->setLogMessage('log.review.reviewFileByProxy', array('reviewerName' => $reviewer->getFullName(), 'articleId' => $reviewAssignment->getSubmissionId(), 'round' => $reviewAssignment->getRound(), 'userName' => $user->getFullName()));
			$entry->setAssocType(ARTICLE_LOG_TYPE_REVIEW);
			$entry->setAssocId($reviewAssignment->getId());

			ArticleLog::logEventEntry($reviewAssignment->getSubmissionId(), $entry);
		}
	}

	/**
	 * Helper method for building submission breadcrumb
	 * @param $articleId
	 * @param $parentPage name of submission component
	 * @return array
	 */
	function submissionBreadcrumb($articleId, $parentPage, $section) {
		$breadcrumb = array();
		if ($articleId) {
			$breadcrumb[] = array(Request::url(null, $section, 'submission', $articleId), "#$articleId", true);
		}

		if ($parentPage) {
			switch($parentPage) {
				case 'summary':
					$parent = array(Request::url(null, $section, 'submission', $articleId), 'submission.summary');
					break;
				case 'review':
					$parent = array(Request::url(null, $section, 'submissionReview', $articleId), 'submission.review');
					break;
				case 'editing':
					$parent = array(Request::url(null, $section, 'submissionEditing', $articleId), 'submission.editing');
					break;
				case 'history':
					$parent = array(Request::url(null, $section, 'submissionHistory', $articleId), 'submission.history');
					break;
			}
			if ($section != 'editor' && $section != 'sectionEditor') {
				$parent[0] = Request::url(null, $section, 'submission', $articleId);
			}
			$breadcrumb[] = $parent;
		}
		return $breadcrumb;
	}

	/**
	 * Notify reviewers of new meeting set by section editor
	 * Added by ayveemallare 7/12/2011
	 */

	function notifyReviewersNewMeeting($meeting, $reviewerIds, $submissionIds, $send = false) {
		$journal =& Request::getJournal();
		$user = & Request::getUser();
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$num=1;
		foreach($submissionIds as $submissionId) {
			$submission = $articleDao->getArticle($submissionId, $journal->getId(), false);
			$submissions = $submissions.$num.". '".$submission->getLocalizedTitle()."' by ".$submission->getAuthorString(true)."\n";
			$num++;
		}
		$userDao =& DAORegistry::getDAO('UserDAO');
		$reviewers = array();
		foreach($reviewerIds as $reviewerId) {
			$reviewer = $userDao->getUser($reviewerId->getReviewerId());
			array_push($reviewers, $reviewer);
		}

		$reviewerAccessKeysEnabled = $journal->getSetting('reviewerAccessKeysEnabled');

		$preventAddressChanges = $reviewerAccessKeysEnabled;

		import('classes.mail.MailTemplate');
		$email = new MailTemplate($reviewerAccessKeysEnabled?'MEETING_NEW':'MEETING_NEW');

		if($preventAddressChanges) {
			$email->setAddressFieldsEnabled(false);
		}

		if($send && !$email->hasErrors()) {
			HookRegistry::call('SectionEditorAction::notifyReviewersNewMeeting', array(&$meeting, &$reviewers, &$submissions, &$email));
				
			if($reviewerAccessKyesEnabled) {
				import('lib.pkp.classes.security.AccessKeyManager');
				import('pages.reviewer.ReviewerHandler');
				$accessKeyManager = new AccessKeyManager();
			}
				
			if($preventAddressChanges) {
				// Ensure that this messages goes to the reviewers, and the reviewers ONLY.
				$email->clearAllRecipients();
				foreach($reviewers as $reviewer) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}
			}
			$email->send();
			return true;
		} else {
			if(!Request::getUserVar('continued') || $preventAddressChanges) {
				foreach($reviewers as $reviewer) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}
			}
			if(!Request::getUserVar('continued')) {
				if($meeting->getDate() != null) {
					$meetingDate = strftime('%B %d, %Y %I:%M %p', strtotime($meeting->getDate()));
				}
				$replyUrl = Request::url(null, 'reviewer', 'viewMeeting', $meeting->getId(), $reviewerAccessKeyEnabled?array('key' => 'ACCESS_KEY'):array());
				$paramArray = array(
					'submissions' => $submissions,
					'meetingDate' => $meetingDate,
					'replyUrl' => $replyUrl,
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'notifyReviewersNewMeeting', $meeting->getId()), array('meetingId' =>$meeting->getId(), 'reviewerIds'=>$reviewerIds, 'submissionsIds'=>$submissionsIds));
			return false;
		}
		return true;
	}

	/**
	 * Notify reviewers of change of schedule in a meeting set by section editor
	 * Added by ayveemallare 7/12/2011
	 */

	function notifyReviewersChangeMeeting($oldDate, $meeting, $reviewerIds, $submissionIds, $send = false) {
		$journal =& Request::getJournal();
		$user = & Request::getUser();
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$num=1;
		foreach($submissionIds as $submissionId) {
			$submission = $articleDao->getArticle($submissionId, $journal->getId(), false);
			$submissions = $submissions.$num.". '".$submission->getLocalizedTitle()."' by ".$submission->getAuthorString(true)."\n";
			$num++;
		}
		$userDao =& DAORegistry::getDAO('UserDAO');
		$reviewers = array();
		foreach($reviewerIds as $reviewerId) {
			$reviewer = $userDao->getUser($reviewerId->getReviewerId());
			array_push($reviewers, $reviewer);
		}

		$reviewerAccessKeysEnabled = $journal->getSetting('reviewerAccessKeysEnabled');

		$preventAddressChanges = $reviewerAccessKeysEnabled;

		import('classes.mail.MailTemplate');
		$email = new MailTemplate($reviewerAccessKeysEnabled?'MEETING_CHANGE':'MEETING_CHANGE');

		if($preventAddressChanges) {
			$email->setAddressFieldsEnabled(false);
		}

		if($send && !$email->hasErrors()) {
			HookRegistry::call('SectionEditorAction::notifyReviewersChangeMeeting', array(&$meeting, &$reviewers, &$submissions, &$email));
				
			if($reviewerAccessKyesEnabled) {
				import('lib.pkp.classes.security.AccessKeyManager');
				import('pages.reviewer.ReviewerHandler');
				$accessKeyManager = new AccessKeyManager();
			}
				
			if($preventAddressChanges) {
				// Ensure that this messages goes to the reviewers, and the reviewers ONLY.
				$email->clearAllRecipients();
				foreach($reviewers as $reviewer) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}
			}
			$email->send();
			return true;
		} else {
			if(!Request::getUserVar('continued') || $preventAddressChanges) {
				foreach($reviewers as $reviewer) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}
			}
			if(!Request::getUserVar('continued')) {
				if($meeting->getDate() != null) {
					$meetingDate = strftime('%B %d, %Y %I:%M %p', strtotime($meeting->getDate()));
				}
				$replyUrl = Request::url(null, 'reviewer', 'viewMeeting', $meeting->getId(), $reviewerAccessKeyEnabled?array('key' => 'ACCESS_KEY'):array());
				$paramArray = array(
					'oldDate' => strftime('%B %d, %Y %I:%M %p', strtotime($oldDate)),
					'submissions' => $submissions,
					'meetingDate' => $meetingDate,
					'replyUrl' => $replyUrl,
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'notifyReviewersChangeMeeting', array($meeting->getId(), $oldDate)), array('oldDate' => $oldDate, 'meetingId' =>$meeting->getId(), 'reviewerIds'=>$reviewerIds, 'submissionsIds'=>$submissionsIds));
			return false;
		}
		return true;
	}

	/**
	 * Notify reviewers if meeting schedule is set to final
	 * Added by ayveemallare 7/12/2011
	 */

	function notifyReviewersFinalMeeting($meeting, $reviewerIds, $submissionIds, $send = false) {
		$journal =& Request::getJournal();
		$user = & Request::getUser();
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$num=1;
		foreach($submissionIds as $submissionId) {
			$submission = $articleDao->getArticle($submissionId, $journal->getId(), false);
			$submissions = $submissions.$num.". '".$submission->getLocalizedTitle()."' by ".$submission->getAuthorString(true)."\n";
			$num++;
		}
		$userDao =& DAORegistry::getDAO('UserDAO');
		$reviewers = array();
		foreach($reviewerIds as $reviewerId) {
			$reviewer = $userDao->getUser($reviewerId->getReviewerId());
			array_push($reviewers, $reviewer);
		}

		$reviewerAccessKeysEnabled = $journal->getSetting('reviewerAccessKeysEnabled');

		$preventAddressChanges = $reviewerAccessKeysEnabled;

		import('classes.mail.MailTemplate');
		$email = new MailTemplate($reviewerAccessKeysEnabled?'MEETING_FINAL':'MEETING_FINAL');

		if($preventAddressChanges) {
			$email->setAddressFieldsEnabled(false);
		}

		if($send && !$email->hasErrors()) {
			HookRegistry::call('SectionEditorAction::notifyReviewersFinalMeeting', array(&$meeting, &$reviewers, &$submissions, &$email));
				
			if($reviewerAccessKyesEnabled) {
				import('lib.pkp.classes.security.AccessKeyManager');
				import('pages.reviewer.ReviewerHandler');
				$accessKeyManager = new AccessKeyManager();
			}
				
			if($preventAddressChanges) {
				// Ensure that this messages goes to the reviewers, and the reviewers ONLY.
				$email->clearAllRecipients();
				foreach($reviewers as $reviewer) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}
			}
			$email->send();
			return true;
		} else {
			if(!Request::getUserVar('continued') || $preventAddressChanges) {
				foreach($reviewers as $reviewer) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}
			}
			if(!Request::getUserVar('continued')) {
				if($meeting->getDate() != null) {
					$meetingDate = strftime('%B %d, %Y %I:%M %p', strtotime($meeting->getDate()));
				}
				$replyUrl = Request::url(null, 'reviewer', 'viewMeeting', $meeting->getId(), $reviewerAccessKeyEnabled?array('key' => 'ACCESS_KEY'):array());
				$paramArray = array(
					'submissions' => $submissions,
					'meetingDate' => $meetingDate,
					'replyUrl' => $replyUrl,
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'notifyReviewersFinalMeeting', $meeting->getId()), array('meetingId' =>$meeting->getId(), 'reviewerIds'=>$reviewerIds, 'submissionsIds'=>$submissionsIds));
			return false;
		}
		return true;
	}

	/**
	 * Notify reviewers if meeting is cancelled
	 * Added by ayveemallare 7/12/2011
	 */

	function notifyReviewersCancelMeeting($meeting, $reviewerIds, $submissionIds, $send = false) {
		$journal =& Request::getJournal();
		$user = & Request::getUser();
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$num=1;
		foreach($submissionIds as $submissionId) {
			$submission = $articleDao->getArticle($submissionId, $journal->getId(), false);
			$submissions = $submissions.$num.". '".$submission->getLocalizedTitle()."' by ".$submission->getAuthorString(true)."\n";
			$num++;
		}
		$userDao =& DAORegistry::getDAO('UserDAO');
		$reviewers = array();
		foreach($reviewerIds as $reviewerId) {
			$reviewer = $userDao->getUser($reviewerId->getReviewerId());
			array_push($reviewers, $reviewer);
		}

		$reviewerAccessKeysEnabled = $journal->getSetting('reviewerAccessKeysEnabled');

		$preventAddressChanges = $reviewerAccessKeysEnabled;

		import('classes.mail.MailTemplate');
		$email = new MailTemplate($reviewerAccessKeysEnabled?'MEETING_CANCEL':'MEETING_CANCEL');

		if($preventAddressChanges) {
			$email->setAddressFieldsEnabled(false);
		}

		if($send && !$email->hasErrors()) {
			HookRegistry::call('SectionEditorAction::notifyReviewersCancelMeeting', array(&$meeting, &$reviewers, &$submissions, &$email));
				
			if($reviewerAccessKyesEnabled) {
				import('lib.pkp.classes.security.AccessKeyManager');
				import('pages.reviewer.ReviewerHandler');
				$accessKeyManager = new AccessKeyManager();
			}
				
			if($preventAddressChanges) {
				// Ensure that this messages goes to the reviewers, and the reviewers ONLY.
				$email->clearAllRecipients();
				foreach($reviewers as $reviewer) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}
			}
			$email->send();
			return true;
		} else {
			if(!Request::getUserVar('continued') || $preventAddressChanges) {
				foreach($reviewers as $reviewer) {
					$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
				}
			}
			if(!Request::getUserVar('continued')) {
				if($meeting->getDate() != null) {
					$meetingDate = strftime('%B %d, %Y %I:%M %p', strtotime($meeting->getDate()));
				}
				$replyUrl = Request::url(null, 'reviewer', 'viewMeeting', $meeting->getId(), $reviewerAccessKeyEnabled?array('key' => 'ACCESS_KEY'):array());
				$paramArray = array(
					'submissions' => $submissions,
					'meetingDate' => $meetingDate,
					'replyUrl' => $replyUrl,
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'notifyReviewersCancelMeeting', $meeting->getId()), array('meetingId' =>$meeting->getId(), 'reviewerIds'=>$reviewerIds, 'submissionsIds'=>$submissionsIds));
			return false;
		}
		return true;
	}

	/**
	 * Remind reviewers of a meeting
	 * Added by ayveemallare 7/12/2011
	 */

	function remindReviewersMeeting($meeting, $reviewerId, $submissionIds, $send = false) {
		$journal =& Request::getJournal();
		$user = & Request::getUser();
		$articleDao =& DAORegistry::getDAO('ArticleDAO');
		$num=1;
		foreach($submissionIds as $submissionId) {
			$submission = $articleDao->getArticle($submissionId, $journal->getId(), false);
			$submissions = $submissions.$num.". '".$submission->getLocalizedTitle()."' by ".$submission->getAuthorString(true)."\n";
			$num++;
		}
		$userDao =& DAORegistry::getDAO('UserDAO');
		$reviewer = $userDao->getUser($reviewerId);

		$reviewerAccessKeysEnabled = $journal->getSetting('reviewerAccessKeysEnabled');

		$preventAddressChanges = $reviewerAccessKeysEnabled;

		import('classes.mail.MailTemplate');
		$email = new MailTemplate($reviewerAccessKeysEnabled?'MEETING_REMIND':'MEETING_REMIND');

		if($preventAddressChanges) {
			$email->setAddressFieldsEnabled(false);
		}

		if($send && !$email->hasErrors()) {
			HookRegistry::call('SectionEditorAction::remindReviewersMeeting', array(&$meeting, &$reviewer, &$submissions, &$email));
				
			if($reviewerAccessKyesEnabled) {
				import('lib.pkp.classes.security.AccessKeyManager');
				import('pages.reviewer.ReviewerHandler');
				$accessKeyManager = new AccessKeyManager();
			}
				
			if($preventAddressChanges) {
				// Ensure that this messages goes to the reviewers, and the reviewers ONLY.
				$email->clearAllRecipients();
				$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
			}
			$email->send();
				
			$meetingReviewerDao =& DAORegistry::getDAO('MeetingReviewerDAO');
			$meetingReviewerDao->updateDateReminded(Core::getCurrentDate(), $reviewerId, $meeting);
			return true;
		} else {
			if(!Request::getUserVar('continued') || $preventAddressChanges) {
				$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
			}
			if(!Request::getUserVar('continued')) {
				if($meeting->getDate() != null) {
					$meetingDate = strftime('%B %d, %Y %I:%M %p', strtotime($meeting->getDate()));
				}
				$replyUrl = Request::url(null, 'reviewer', 'viewMeeting', $meeting->getId(), $reviewerAccessKeyEnabled?array('key' => 'ACCESS_KEY'):array());
				$paramArray = array(
					'reviewerName' => $reviewer->getFullName(),
					'submissions' => $submissions,
					'meetingDate' => $meetingDate,
					'replyUrl' => $replyUrl,
					'editorialContactSignature' => $user->getContactSignature()
				);
				$email->assignParams($paramArray);
			}
			$email->displayEditForm(Request::url(null, null, 'remindReviewersMeeting', $meeting->getId()), array('meetingId' =>$meeting->getId(), 'reviewerId'=>$reviewerId, 'submissionsIds'=>$submissionsIds));
			return false;
		}
		return true;
	}
	
	/**
	 * Upload final decision file for expedited review as supplementary file with type "Final Decision File"
	 * 2/4/2012
	 */
	function uploadDecisionFile($articleId, $fileName) {
		$journal =& Request::getJournal();
		$this->validate($articleId);

		import('classes.file.ArticleFileManager');
		$articleFileManager = new ArticleFileManager($articleId);
		$suppFileDao =& DAORegistry::getDAO('SuppFileDAO');
		
		$type = "Final Decision";
		
        // Upload file, if file selected.
		if ($articleFileManager->uploadedFileExists($fileName)) {
			$fileId = $articleFileManager->uploadSuppFile($fileName);
			import('classes.search.ArticleSearchIndex');
			ArticleSearchIndex::updateFileIndex($articleId, ARTICLE_SEARCH_SUPPLEMENTARY_FILE, $fileId);
		} else {
			$fileId = 0;
		}

		//Insert new supplementary file
		$suppFile = new SuppFile();
		$suppFile->setArticleId($articleId);
		$suppFile->setFileId($fileId);
		$suppFile->setType($type);		

		$suppFileDao->insertSuppFile($suppFile);
		/*import('classes.submission.form.SuppFileForm');
		$suppFileForm = new SuppFileForm($submission, $journal);
		$suppFileForm->setData('title', array($submission->getLocale() => Locale::translate('common.untitled')));
		$suppFileForm->setData('type', $type);
		$suppFileId = $suppFileForm->execute($fileName);
		*/
		return $suppFile->getId();			
	}
	
}

?>
