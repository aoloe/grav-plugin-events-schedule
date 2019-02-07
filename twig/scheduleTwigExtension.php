<?php
namespace Grav\Plugin;
class ScheduleTwigExtension extends \Twig_Extension
{
    public function setEventsSchedule($eventsSchedulePlugin)
    {
        $this->eventsSchedulePlugin = $eventsSchedulePlugin;
    }

    public function getName()
    {
        return 'ScheduleTwigExtension';
    }
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('eventsScheduleNextDates', [$this->eventsSchedulePlugin, 'getNextDates']),
            new \Twig_SimpleFunction('eventsScheduleNextEvents', [$this->eventsSchedulePlugin, 'getNextEvents']),
            new \Twig_SimpleFunction('eventsScheduleNextIcs', [$this->eventsSchedulePlugin, 'getNextIcs'])
        ];
    }
}
