<?php

namespace Sylphian\Map\Widget;

use Sylphian\Map\Repository\MapMarkerRepository;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class EventsWidget extends AbstractWidget
{
	public function render(): string|WidgetRenderer
	{
		/** @var MapMarkerRepository $markerRepo */
		$markerRepo = $this->repository('Sylphian\Map:MapMarker');
		$events = $markerRepo->getEventMarkersForWidget();

		$viewParams = [
			'events' => $events,
		];

		return $this->renderer('sylphian_map_widget_events', $viewParams);
	}
}
