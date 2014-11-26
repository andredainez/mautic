<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Helper;

class AbTestHelper
{

    /**
     * Determines the winner of A/B test based on open rate
     *
     * @param $factory
     * @param $parent
     * @param $properties
     *
     * @return array
     */
    public static function determineOpenRateWinner ($factory, $parent, $children)
    {
        /** @var \Mautic\EmailBundle\Entity\StatRepository $repo */
        $repo = $factory->getEntityManager()->getRepository('MauticEmailBundle:Stat');

        $ids = array($parent->getId());

        foreach ($children as $c) {
            if ($c->isPublished()) {
                $ids[] = $c->getId();
            }
        }

        $startDate = $parent->getVariantStartDate();
        if ($startDate != null && !empty($ids)) {
            //get their bounce rates
            $counts = $repo->getOpenedRates($ids, $startDate);

            $translator = $factory->getTranslator();
            if ($counts) {
                $rates      = $support = $data = array();
                $hasResults = array();

                $parentId = $parent->getId();
                foreach ($counts as $id => $stats) {
                    $subject                                                           = ($parentId === $id) ? $parent->getSubject() : $children[$id]->getSubject();
                    $support['labels'][]                                               = $id . ':' . $subject . ' (' . $stats['readRate'] . '%)';
                    $rates[$id]                                                        = $stats['readRate'];
                    $data[$translator->trans('mautic.email.abtest.label.opened')][]    = $stats['readCount'];
                    $data[$translator->trans('mautic.email.abtest.label.sent')][]      = $stats['totalCount'];
                    $hasResults[]                                                      = $id;
                }

                if (!in_array($parent->getId(), $hasResults)) {
                    //make sure that parent and published children are included
                    $support['labels'][] = $parent->getId() . ':' . $parent->getSubject() . ' (0%)';

                    $data[$translator->trans('mautic.email.abtest.label.opened')][]    = 0;
                    $data[$translator->trans('mautic.email.abtest.label.sent')][]      = 0;
                }

                foreach ($children as $c) {
                    if ($c->isPublished()) {
                        if (!in_array($c->getId(), $hasResults)) {
                            //make sure that parent and published children are included
                            $support['labels'][]                                               = $c->getId() . ':' . $c->getSubject() . ' (0%)';
                            $data[$translator->trans('mautic.email.abtest.label.opened')][]    = 0;
                            $data[$translator->trans('mautic.email.abtest.label.sent')][]      = 0;
                        }
                    }
                }
                $support['data'] = $data;

                //set max for scales
                $maxes = array();
                foreach ($support['data'] as $label => $data) {
                    $maxes[] = max($data);
                }
                $top                   = max($maxes);
                $support['step_width'] = (floor($top / 10) * 10) / 10;

                //put in order from least to greatest just because
                asort($rates);

                //who's the winner?
                $max = max($rates);

                //get the page ids with the most number of downloads
                $winners = ($max > 0) ? array_keys($rates, $max) : array();

                return array(
                    'winners'         => $winners,
                    'support'         => $support,
                    'basedOn'         => 'email.openrate',
                    'supportTemplate' => 'MauticPageBundle:SubscribedEvents\AbTest:bargraph.html.php'
                );
            }
        }

        return array(
            'winners' => array(),
            'support' => array(),
            'basedOn' => 'email.openrate'
        );
    }


    /**
     * Determines the winner of A/B test based on clickthrough rates
     *
     * @param $factory
     * @param $parent
     * @param $properties
     *
     * @return array
     */
    public static function determineClickthroughRateWinner ($factory, $parent, $children)
    {
        /** @var \Mautic\PageBundle\Entity\HitRepository $pageRepo */
        $pageRepo = $factory->getEntityManager()->getRepository('MauticPageBundle:Hit');

        /** @var \Mautic\EmailBundle\Entity\StatRepository $emailRepo */
        $emailRepo = $factory->getEntityManager()->getRepository('MauticEmailBundle:Stat');

        $ids = array($parent->getId());

        foreach ($children as $c) {
            if ($c->isPublished()) {
                $ids[] = $c->getId();
            }
        }

        $startDate = $parent->getVariantStartDate();
        if ($startDate != null && !empty($ids)) {
            //get their bounce rates
            $clickthroughCounts = $pageRepo->getEmailClickthroughHitCount($ids, $startDate);
            $sentCounts         = $emailRepo->getSentCounts($ids, $startDate);

            $translator = $factory->getTranslator();
            if ($clickthroughCounts) {
                $rates      = $support = $data = array();
                $hasResults = array();

                $parentId = $parent->getId();
                foreach ($clickthroughCounts as $id => $count) {
                    if (!isset($sentCounts[$id])) {
                        $sentCounts[$id] = 0;
                    }

                    $rates[$id] = $sentCounts[$id] ? round(($count / $sentCounts[$id]) * 100, 2) : 0;

                    $subject             = ($parentId === $id) ? $parent->getSubject() : $children[$id]->getSubject();
                    $support['labels'][] = $id . ':' . $subject . ' (' . $rates[$id] . '%)';

                    $data[$translator->trans('mautic.email.abtest.label.clickthrough')][]      = $count;
                    $data[$translator->trans('mautic.email.abtest.label.sent')][]              = $sentCounts[$id];
                    $hasResults[]                                                              = $id;
                }

                if (!in_array($parent->getId(), $hasResults)) {
                    //make sure that parent and published children are included
                    $support['labels'][] = $parent->getId() . ':' . $parent->getSubject() . ' (0%)';

                    $data[$translator->trans('mautic.email.abtest.label.clickthrough')][]      = 0;
                    $data[$translator->trans('mautic.email.abtest.label.sent')][]              = 0;
                }

                foreach ($children as $c) {
                    if ($c->isPublished()) {
                        if (!in_array($c->getId(), $hasResults)) {
                            //make sure that parent and published children are included
                            $support['labels'][]                                                       = $c->getId() . ':' . $c->getSubject() . ' (0%)';
                            $data[$translator->trans('mautic.email.abtest.label.clickthrough')][]      = 0;
                            $data[$translator->trans('mautic.email.abtest.label.sent')][]              = 0;
                        }
                    }
                }
                $support['data'] = $data;

                //set max for scales
                $maxes = array();
                foreach ($support['data'] as $label => $data) {
                    $maxes[] = max($data);
                }
                $top                   = max($maxes);
                $support['step_width'] = (floor($top / 10) * 10) / 10;

                //put in order from least to greatest just because
                asort($rates);

                //who's the winner?
                $max = max($rates);

                //get the page ids with the most number of downloads
                $winners = ($max > 0) ? array_keys($rates, $max) : array();

                return array(
                    'winners'         => $winners,
                    'support'         => $support,
                    'basedOn'         => 'email.clickthrough',
                    'supportTemplate' => 'MauticPageBundle:SubscribedEvents\AbTest:bargraph.html.php'
                );
            }
        }

        return array(
            'winners' => array(),
            'support' => array(),
            'basedOn' => 'email.clickthrough'
        );
    }
}