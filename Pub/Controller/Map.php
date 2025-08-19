<?php

namespace Sylphian\Map\Pub\Controller;

use Sylphian\Map\Repository\MapMarkerRepository as MapMarkerRepository;
use Sylphian\Map\Repository\MapMarkerSuggestionRepository as MapMarkerSuggestionRepository;
use XF;
use XF\ControllerPlugin\DeletePlugin;
use XF\Mvc\Controller;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;
use XF\PrintableException;

class Map extends Controller
{
    protected function getMapMarkerRepo(): MapMarkerRepository
    {
        /** @var MapMarkerRepository $repo */
        $repo = XF::repository('Sylphian\Map:MapMarker');
        return $repo;
    }

    protected function getMapMarkerSuggestionRepo(): MapMarkerSuggestionRepository
    {
        /** @var MapMarkerSuggestionRepository $repo */
        $repo = XF::repository('Sylphian\Map:MapMarkerSuggestion');
        return $repo;
    }

    public function actionIndex(): View
    {
        $markerRepo = $this->getMapMarkerRepo();

        $visitor = XF::visitor();
        $canManageMarkers = $visitor->hasPermission('general', 'manageMapMarkers');

        $markersCollection = $canManageMarkers
            ? $markerRepo->getAllMapMarkers()
            : $markerRepo->getActiveMapMarkers();

        $processedData = $markerRepo->processMarkersForDisplay($markersCollection, $canManageMarkers);

        $pendingSuggestions = [];
        if ($canManageMarkers) {
            $suggestionRepo = $this->getMapMarkerSuggestionRepo();
            $pendingSuggestions = $suggestionRepo->getPendingSuggestions()->count();
        }

        $viewParams = [
            'markers' => $processedData['markers'],
            'markerTypes' => $processedData['markerTypes'],
            'canManageMarkers' => $canManageMarkers,
            'pendingSuggestions' => $pendingSuggestions,
            'mapCenter' => [
                'lat' => XF::options()->map_starting_lat,
                'lng' => XF::options()->map_starting_lng
            ],
            'startingZoom' => XF::options()->map_starting_zoom,
            'minZoom' => XF::options()->map_min_zoom,
            'maxZoom' => XF::options()->map_max_zoom,
        ];

        return $this->view('Sylphian\Map:Map', 'sylphian_map', $viewParams);
    }

    /**
     * Adds a new map marker
     *
     * Displays the marker creation form or processes form submission.
     * Validation is handled in the repository layer.
     *
     * @return Redirect|View|Error Redirects to the map page on success or displays the form
     * @throws PrintableException If marker creation fails
     */
    public function actionAdd(): Redirect|View|Error
    {
        if (!$this->isPost()) {
            $viewParams = [
                'entity' => $this->em()->create('Sylphian\Map:MapMarker'),
                'formAction' => $this->buildLink('map/add'),
                'formType' => 'add_form_title',
                'canManageActive' => XF::visitor()->hasPermission('general', 'manageMapMarkers')
            ];
            return $this->view('Sylphian\Map:Marker\Form', 'sylphian_map_marker_form', $viewParams);
        }

        $input = $this->filter([
            'title' => 'str',
            'content' => 'str',
            'lat' => 'float',
            'lng' => 'float',
            'type' => 'str',
            'icon' => 'str',
            'icon_var' => 'str',
            'icon_color' => 'str',
            'marker_color' => 'str',
            'active' => 'bool',
            'create_thread' => 'bool'
        ]);

        $markerRepo = $this->getMapMarkerRepo();

        $markerData = [
            'title' => $input['title'],
            'content' => $input['content'],
            'lat' => $input['lat'],
            'lng' => $input['lng'],
            'type' => $input['type'],
            'icon' => $input['icon'],
            'icon_var' => $input['icon_var'],
            'icon_color' => $input['icon_color'],
            'marker_color' => $input['marker_color'],
            'active' => $input['active'],
            'user_id' => XF::visitor()->user_id
        ];

        $marker = $markerRepo->createMapMarker($markerData);

        if ($input['create_thread']) {
            $markerRepo->createThreadForMarker($marker);
        }

        return $this->redirect($this->buildLink('map'));
    }

    /**
     * Edits an existing map marker
     *
     * Retrieves a marker by ID, displays the edit form, or processes the edit submission.
     * Validation is handled in the repository layer.
     *
     * @throws PrintableException If marker retrieval or update fails
     * @return Redirect|View Redirects to the map page on success or displays the edit form
     */
    public function actionEdit(): Redirect|View
    {
        $markerRepo = $this->getMapMarkerRepo();
        $markerId = $this->filter('marker_id', 'uint');
        $marker = $markerRepo->getMarkerOrFail($markerId);

        if (!$this->isPost()) {
            $viewParams = [
                'entity' => $marker,
                'formAction' => $this->buildLink('map/edit', null, ['marker_id' => $markerId]),
                'formType' => 'edit_form_title',
                'canManageActive' => XF::visitor()->hasPermission('general', 'manageMapMarkers')
            ];
            return $this->view('Sylphian\Map:Marker\Form', 'sylphian_map_marker_form', $viewParams);
        }

        $input = $this->filter([
            'title' => 'str',
            'content' => 'str',
            'lat' => 'float',
            'lng' => 'float',
            'type' => 'str',
            'icon' => 'str',
            'icon_var' => 'str',
            'icon_color' => 'str',
            'marker_color' => 'str',
            'active' => 'bool'
        ]);

        $markerRepo->updateMapMarker($marker, $input);

        return $this->redirect($this->buildLink('map'));
    }

    /**
     * Deletes an existing map marker
     *
     * Retrieves a marker by ID, displays a confirmation form, or processes the deletion.
     * Error handling is managed in the repository layer.
     *
     * @throws PrintableException If marker retrieval fails
     * @return Redirect|View|Error Redirects to the map page on success, displays an error,
     *                            or shows the deletion confirmation form
     */
    public function actionDelete(): Redirect|View|Error
    {
        $markerRepo = $this->getMapMarkerRepo();

        $markerId = $this->filter('marker_id', 'uint');
        $marker = $markerRepo->getMarkerOrFail($markerId);

        /** @var DeletePlugin $plugin */
        $plugin = $this->plugin('XF:DeletePlugin');

        return $plugin->actionDelete(
            $marker,
            $this->buildLink('map/delete', null, [
                'marker_id' => $markerId
            ]),
            $this->buildLink('map/management'),
            $this->buildLink('map'),
            $marker->title,
            null,
            [
                'deletionImportantPhrase' => 'confirm_delete_marker'
            ]
        );
    }

    /**
     * Creates a new map marker suggestion
     *
     * Displays the suggestion form or processes the suggestion submission.
     * Validation and status management are handled in the repository layer.
     *
     * @throws PrintableException If suggestion creation fails
     * @return Redirect|View Redirects to the map page on success or displays the suggestion form
     */
    public function actionSuggest(): Redirect|View
    {
        if (!$this->isPost()) {
            $viewParams = [
                'entity' => $this->em()->create('Sylphian\Map:MapMarkerSuggestion'),
                'formAction' => $this->buildLink('map/suggest'),
                'formType' => 'suggest_form_title',
                'canManageActive' => false
            ];
            return $this->view('Sylphian\Map:Marker\Form', 'sylphian_map_marker_form', $viewParams);
        }

        $input = $this->filter([
            'title' => 'str',
            'content' => 'str',
            'lat' => 'float',
            'lng' => 'float',
            'type' => 'str',
            'icon' => 'str',
            'icon_var' => 'str',
            'icon_color' => 'str',
            'marker_color' => 'str',
            'create_thread' => 'bool'
        ]);

        $suggestionRepo = $this->getMapMarkerSuggestionRepo();

        $input['user_id'] = XF::visitor()->user_id;
        $suggestionRepo->createSuggestion($input);

        return $this->redirect($this->buildLink('map'));
    }

    /**
     * Approves a marker suggestion, creating a permanent marker
     *
     * @throws PrintableException If suggestion retrieval fails
     * @return Redirect|View|Error
     */
    public function actionApproveSuggestion(): Redirect|View|Error
    {
        $suggestionRepo = $this->getMapMarkerSuggestionRepo();
        $suggestionId = $this->filter('suggestion_id', 'uint');

        if (!$this->isPost()) {
            $suggestion = $suggestionRepo->getSuggestionOrFail($suggestionId);
            $viewParams = [
                'suggestion' => $suggestion,
                'actionType' => 'approve',
                'processTitle' => 'sylphian_map_suggestion_process_approve_title',
            ];
            return $this->view('Sylphian\Map:Suggestion\Process', 'sylphian_map_suggestion_process', $viewParams);
        }

        if (!$suggestionRepo->approveSuggestion($suggestionId)) {
            return $this->error(XF::phrase('error_occurred_while_approving_suggestion'));
        }

        return $this->redirect($this->buildLink('map'));
    }

    /**
     * Rejects a marker suggestion
     *
     * @throws PrintableException If suggestion retrieval fails
     * @return Redirect|View|Error
     */
    public function actionRejectSuggestion(): Redirect|View|Error
    {
        $suggestionRepo = $this->getMapMarkerSuggestionRepo();
        $suggestionId = $this->filter('suggestion_id', 'uint');

        if (!$this->isPost()) {
            $suggestion = $suggestionRepo->getSuggestionOrFail($suggestionId);
            $viewParams = [
                'suggestion' => $suggestion,
                'actionType' => 'reject',
                'processTitle' => 'sylphian_map_suggestion_process_reject_title'
            ];
            return $this->view('Sylphian\Map:Suggestion\Process', 'sylphian_map_suggestion_process', $viewParams);
        }

        if (!$suggestionRepo->rejectSuggestion($suggestionId)) {
            return $this->error(XF::phrase('error_occurred_while_rejecting_suggestion'));
        }

        return $this->redirect($this->buildLink('map'));
    }

    /**
     * Displays the map management interface
     * Allows administrators to view, edit, add, and delete markers
     *
     * @return View The management view with marker data and pagination
     */
    public function actionManagement(): View
    {
        $markerRepo = $this->getMapMarkerRepo();
        $suggestionRepo = $this->getMapMarkerSuggestionRepo();

        $page = $this->filterPage();
        $perPage = XF::options()->map_markers_per_page ?? 20;

        $totalMarkers = 0;
        $markersCollection = $markerRepo->getAllMapMarkers('User', $page, $perPage, $totalMarkers);
        $processedData = $markerRepo->processMarkersForDisplay($markersCollection, true);

        $pendingSuggestions = $suggestionRepo->getPendingSuggestions('User')->toArray();

        $viewParams = [
            'allMarkers' => $processedData['allMarkers'],
            'pendingSuggestions' => $pendingSuggestions,
            'canManageMarkers' => true,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalMarkers,
            'pageNavParams' => []
        ];

        return $this->view('Sylphian\Map:Management', 'sylphian_map_management', $viewParams);
    }

    /**
     * Displays the suggestion management interface
     * Allows administrators to view and process marker suggestions
     *
     * @return View The suggestions view with pending suggestions and pagination
     */
    public function actionSuggestion(): View
    {
        $suggestionRepo = $this->getMapMarkerSuggestionRepo();

        $page = $this->filterPage();
        $perPage = XF::options()->map_markers_per_page ?? 20;

        $totalSuggestions = 0;
        $pendingSuggestions = $suggestionRepo->getPendingSuggestions('User', $page, $perPage, $totalSuggestions)->toArray();

        $viewParams = [
            'pendingSuggestions' => $pendingSuggestions,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalSuggestions,
            'pageNavParams' => []
        ];

        return $this->view('Sylphian\Map:Suggestions', 'sylphian_map_suggestions', $viewParams);
    }
}