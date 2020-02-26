<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;

use Grav\Common\File\CompiledYamlFile;

use Grav\Plugin\EventsIcsPlugin as ICS;

/**
 * Class EventsSchedulePlugin
 * @package Grav\Plugin
 */
class EventsSchedulePlugin extends Plugin
{

    private $dataDirectory = 'events-schedule';
    private $dataFilename = 'schedule.yaml';
    private $domain = 'bc-oberurdorf.ch';
    private $schedule = null;

    private $default = [
        'label' => null,
        'start' => null,
        'end' => null,
        'location' => null,
        'url' => null,
    ];

    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onTwigExtensions' => ['onTwigExtensions', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            // 'onPageContentRaw' => ['onPageContentRaw', 0]
        ]);

        // read the defaults from the config file
        $default = Grav::instance()['config']->get('plugins.events-schedule');
        if ($default && array_key_exists('default', $default)) {
            $this->default = $default['default'] + $this->default;
        }
    }

    /**
     * The twig templates call plugin specific functions.
     */
    public function onTwigExtensions()
    {
        require_once(__DIR__ . '/twig/scheduleTwigExtension.php');
        $scheduleTwigExtension = new ScheduleTwigExtension();
        $scheduleTwigExtension->setEventsSchedule($this);
        $this->grav['twig']->twig->addExtension($scheduleTwigExtension);
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Currently, this does nothing. But we might want page
     * specific settings.
     */
    /*
    public function onPageContentRaw(Event $event)
    {
        $page = $event['page'];
        $config = $this->mergeConfig($page);

        if (!$config->get('active')) {
            return;
        }

        $this->dataDirectory = 'events-schedule';
        // TODO: one should be able to set the name of the schedule file
        $this->dataFilename = 'schedule.yaml';

    }
    */


    /**
     * Get the next $count dates.
     * Called from the Twig templates.
     */
    public function getNextDates($count = null)
    {
        $result = [];
        $this->readSchedule();
        $today = date('Ymd');
        $i = 0;
        $language = Grav::instance()['language'];

        foreach ($this->schedule as $row) {
            if ($row['date'] >= $today) {
                $i++;
                $date = getdate(strtotime($row['date']));
                $active = true; // TODO: rename this?
                if (array_key_exists('label', $row)) {
                    $label = $row['label'];
                    $active = array_key_exists('event', $row);
                } else {
                    $label = $this->default['label'];
                }
                $result[] = [
                    'day' => $date['mday'],
                    'month' => $language->translateArray('GRAV.MONTHS_OF_THE_YEAR',
                        $date['mon'] - 1),
                    'active' => $active,
                    'label' => $label
                ];

                if (!is_null($count) && $i >= $count) {
                    return $result;
                }
            }
        }
        return $result;
    }

    /**
     * Get the next $count events.
     * Called from the Twig templates.
     */
    public function getNextEvents($count = null)
    {
        $result = [];
        $this->readSchedule();
        $today = date('Ymd');
        $i = 0;
        $language = Grav::instance()['language'];

        foreach ($this->schedule as $row) {
            if (array_key_exists('event', $row) && $row['date'] >= $today) {
                $i++;
                $date = getdate(strtotime($row['date']));
                $result[] = [
                    'day' => $date['mday'],
                    'month' => $language->translateArray('GRAV.MONTHS_OF_THE_YEAR',
                        $date['mon'] - 1),
                    'year' => $date['year'],
                    'label' => $row['label']
                ];

                if (!is_null($count) && $i >= $count) {
                    return $result;
                }
            }
        }
        return $result;
    }

    /**
     * Get the next $count events for the ICS (iCal) outpupt.
     * Called from the Twig templates.
     */
    public function getNextIcs($count = null)
    {
        $result = [];
        $this->readSchedule();
        $today = date('Ymd');
        $i = 0;
        $language = Grav::instance()['language'];

        function get($row, $key, $default) {
            return array_key_exists($key, $row) ? $row[$key] : $default;
        }

        $timestamp = ICS::getIcsDateFromIso(date('Ymd His', $this->dataFile->modified()));
        foreach ($this->schedule as $row) {
            if ($row['date'] >= $today) {
                $i++;
                $timeStart = get($row, 'start', $this->default['start']);
                $timeEnd = get($row, 'end', $this->default['end']);
                $label = get($row, 'label', $this->default['label']);
                $id = 
                $result[] = [
                    'start' => ICS::getIcsDateFromIso($row['date']. ' '.$timeStart),
                    'end' => ICS::getIcsDateFromIso($row['date']. ' '.$timeEnd),
                    'id' => base64_encode($row['date'].$label).'@'.$this->domain,
                    'timestamp' => $timestamp,
                    'location' => ICS::getIcsStringEscaped($this->default['location']),
                    'description' => '', // TODO: check that switching summary and description works with google calendars
                    'url' => $this->default['url'],
                    'summary' => ICS::getIcsStringEscaped($label),
                ];
                if (!is_null($count) && $i >= $count) {
                    return $result;
                }
            }
        }
        return $result;
    }

    private function readSchedule()
    {
        if (is_null($this->schedule)) {
            $this->dataFile = CompiledYamlFile::instance($this->grav['locator']->findResource("user://data/" . $this->dataDirectory . "/" . $this->dataFilename));
            $this->schedule = $this->dataFile->content();
        }
    }
}
