// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Preview page functionality
 *
 * @module     local_ai_quiz/preview
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    return {
        init: function() {
            // Select all checkboxes
            $('#select-all').on('click', function() {
                $('.question-checkbox').prop('checked', true);
            });

            // Deselect all checkboxes
            $('#deselect-all').on('click', function() {
                $('.question-checkbox').prop('checked', false);
            });

            // Delete question
            $('.delete-question').on('click', function() {
                var qid = $(this).data('qid');
                var questionCard = $(this).closest('.question-card');

                if (confirm('Are you sure you want to delete this question?')) {
                    var sessionkey = new URLSearchParams(window.location.search).get('sessionkey');
                    var url = window.location.pathname +
                        '?sessionkey=' + sessionkey +
                        '&action=delete' +
                        '&qid=' + qid +
                        '&sesskey=' + M.cfg.sesskey;

                    window.location.href = url;
                }
            });

            // Inline editing - save on blur
            $('.editable').on('blur', function() {
                var element = $(this);
                var questionCard = element.closest('.question-card');
                var qid = questionCard.data('qid');
                var field = element.data('field');
                var value = element.text().trim();

                // Don't save if empty
                if (!value) {
                    Notification.alert('Error', 'Field cannot be empty', 'OK');
                    return;
                }

                // Save via AJAX
                var sessionkey = new URLSearchParams(window.location.search).get('sessionkey');

                $.ajax({
                    url: window.location.pathname,
                    type: 'POST',
                    data: {
                        sessionkey: sessionkey,
                        action: 'edit',
                        qid: qid,
                        field: field,
                        value: value,
                        sesskey: M.cfg.sesskey
                    },
                    success: function(response) {
                        try {
                            var result = JSON.parse(response);
                            if (result.success) {
                                // Visual feedback
                                element.css('background-color', '#d4edda');
                                setTimeout(function() {
                                    element.css('background-color', '');
                                }, 1000);
                            }
                        } catch (e) {
                            Notification.alert('Error', 'Failed to save changes', 'OK');
                        }
                    },
                    error: function() {
                        Notification.alert('Error', 'Failed to save changes', 'OK');
                    }
                });
            });

            // Change correct answer
            $('.correct-answer-radio').on('change', function() {
                var radio = $(this);
                var qid = radio.data('qid');
                var newAnswer = radio.val();
                var questionCard = radio.closest('.question-card');
                var sessionkey = new URLSearchParams(window.location.search).get('sessionkey');

                // Update styling
                questionCard.find('.options-list li > div > div').removeClass('list-group-item-success');
                radio.closest('li').find('> div > div.editable').addClass('list-group-item-success');

                // Save via AJAX
                $.ajax({
                    url: window.location.pathname,
                    type: 'POST',
                    data: {
                        sessionkey: sessionkey,
                        action: 'edit',
                        qid: qid,
                        field: 'correct_answer',
                        value: newAnswer,
                        sesskey: M.cfg.sesskey
                    },
                    success: function(response) {
                        try {
                            var result = JSON.parse(response);
                            if (!result.success) {
                                Notification.alert('Error', 'Failed to update correct answer', 'OK');
                            }
                        } catch (e) {
                            Notification.alert('Error', 'Failed to update correct answer', 'OK');
                        }
                    }
                });
            });

            // Form validation
            $('#preview-form').on('submit', function(e) {
                var selectedCount = $('.question-checkbox:checked').length;
                if (selectedCount === 0) {
                    e.preventDefault();
                    Notification.alert('Error', 'Please select at least one question to import', 'OK');
                    return false;
                }
                return true;
            });
        }
    };
});
