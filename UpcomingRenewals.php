<?php

namespace WHMCS\Module\Widget;

use App;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("No direct access");
}

class UpcomingRenewals extends \WHMCS\Module\AbstractWidget
{
    protected $title = '📅 Upcoming Renewals';
    protected $description = 'Shows hosting services due for renewal';
    protected $weight = 150;
    protected $cache = false;

    public function getData()
    {
        $today = date('Y-m-d');

        $fromDate = App::getFromRequest('renewal_from') ?: $today;
        $toDate   = App::getFromRequest('renewal_to')   ?: date('Y-m-d', strtotime('+30 days'));

        $fromDate = date('Y-m-d', strtotime($fromDate));
        $toDate   = date('Y-m-d', strtotime($toDate));

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $in7days = date('Y-m-d', strtotime('+7 days'));

        $base = Capsule::table('tblhosting')
            ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
            ->where('tblhosting.domainstatus', 'Active')
            ->whereBetween('tblhosting.nextduedate', [$fromDate, $toDate]);

        $count = (clone $base)->count();

        $count7 = Capsule::table('tblhosting')
            ->where('domainstatus', 'Active')
            ->whereBetween('nextduedate', [$today, $in7days])
            ->count();

        $upcoming = (clone $base)
            ->select([
                'tblhosting.id',
                'tblhosting.domain',
                'tblhosting.nextduedate',
                'tblhosting.amount',
                'tblhosting.billingcycle',
                'tblclients.id as client_id',
                'tblclients.firstname',
                'tblclients.lastname',
            ])
            ->orderBy('tblhosting.nextduedate', 'asc')
            ->limit(15)
            ->get();

        $totalRevenue = (clone $base)->sum('tblhosting.amount');

        return [
            'count'        => $count,
            'count7'       => $count7,
            'upcoming'     => $upcoming,
            'totalRevenue' => number_format((float) $totalRevenue, 2),
            'fromDate'     => $fromDate,
            'toDate'       => $toDate,
        ];
    }

    public function generateOutput($data)
    {
        $rows     = '';
        $fromDate = $data['fromDate'];
        $toDate   = $data['toDate'];
        $today    = date('Y-m-d');

        foreach ($data['upcoming'] as $service) {
            $dueDate    = date('d M Y', strtotime($service->nextduedate));
            $daysLeft   = (int) ceil((strtotime($service->nextduedate) - time()) / 86400);
            $clientName = htmlspecialchars($service->firstname . ' ' . $service->lastname);
            $domain     = htmlspecialchars($service->domain ?: '(No Domain)');
            $amount     = number_format((float) $service->amount, 2);
            $serviceUrl = 'clientsservices.php?userid=' . $service->client_id . '&id=' . $service->id;

            if ($daysLeft <= 3) {
                $badgeColor = '#e74c3c';
            } elseif ($daysLeft <= 7) {
                $badgeColor = '#e67e22';
            } else {
                $badgeColor = '#27ae60';
            }

            $badge = "<span style='background:{$badgeColor};color:#fff;padding:2px 8px;
                border-radius:12px;font-size:11px;'>{$daysLeft}d</span>";

            $rows .= "
            <tr style='border-bottom:1px solid #f0f0f0;'>
                <td style='padding:8px 6px;'>
                    <a href='{$serviceUrl}' style='font-weight:600;color:#3498db;text-decoration:none;'>
                        {$domain}
                    </a>
                    <div style='font-size:11px;color:#999;margin-top:2px;'>{$clientName}</div>
                </td>
                <td style='padding:8px 6px;color:#555;font-size:12px;white-space:nowrap;'>{$dueDate}</td>
                <td style='padding:8px 6px;text-align:center;'>{$badge}</td>
                <td style='padding:8px 6px;text-align:right;color:#27ae60;font-weight:600;font-size:13px;'>
                    ₹{$amount}
                </td>
            </tr>";
        }

        if (!$rows) {
            $rows = "<tr><td colspan='4' style='text-align:center;padding:24px;color:#bbb;
                font-size:13px;'>No renewals found for selected date range</td></tr>";
        }

        // --- Quick filter date ranges ---
        $thisMonthStart = date('Y-m-01');
        $thisMonthEnd   = date('Y-m-t');
        $nextMonthStart = date('Y-m-01', strtotime('first day of next month'));
        $nextMonthEnd   = date('Y-m-t',  strtotime('last day of next month'));
        $next3End       = date('Y-m-t',  strtotime('+3 months'));
        $next6End       = date('Y-m-t',  strtotime('+6 months'));
        $next12End      = date('Y-m-t',  strtotime('+12 months'));

        $today7  = date('Y-m-d', strtotime('+7 days'));
        $today15 = date('Y-m-d', strtotime('+15 days'));
        $today30 = date('Y-m-d', strtotime('+30 days'));

        // Button styles
        $btn         = "display:inline-block;padding:3px 9px;border-radius:4px;font-size:11px;cursor:pointer;border:1px solid #ddd;margin:2px 2px;text-decoration:none;white-space:nowrap;";
        $activeBtn   = $btn . "background:#3498db;color:#fff;border-color:#3498db;font-weight:600;";
        $inactiveBtn = $btn . "background:#fff;color:#555;";

        // Determine active button
        $s7         = ($fromDate == $today    && $toDate == $today7)        ? $activeBtn : $inactiveBtn;
        $s15        = ($fromDate == $today    && $toDate == $today15)       ? $activeBtn : $inactiveBtn;
        $s30        = ($fromDate == $today    && $toDate == $today30)       ? $activeBtn : $inactiveBtn;
        $sThisMonth = ($fromDate == $thisMonthStart && $toDate == $thisMonthEnd)   ? $activeBtn : $inactiveBtn;
        $sNextMonth = ($fromDate == $nextMonthStart && $toDate == $nextMonthEnd)   ? $activeBtn : $inactiveBtn;
        $s3m        = ($fromDate == $today    && $toDate == $next3End)      ? $activeBtn : $inactiveBtn;
        $s6m        = ($fromDate == $today    && $toDate == $next6End)      ? $activeBtn : $inactiveBtn;
        $s12m       = ($fromDate == $today    && $toDate == $next12End)     ? $activeBtn : $inactiveBtn;

        $count7       = $data['count7'];
        $count        = $data['count'];
        $totalRevenue = $data['totalRevenue'];

        return <<<HTML
<div style="font-family:sans-serif;padding:12px 14px;">

    <!-- Filter Box -->
    <div style="background:#f8f9fa;border:1px solid #eee;border-radius:8px;padding:10px 14px;margin-bottom:14px;">

        <!-- Row 1: Day-based quick filters -->
        <div style="margin-bottom:8px;display:flex;align-items:center;flex-wrap:wrap;gap:2px;">
            <span style="font-size:11px;color:#999;margin-right:4px;white-space:nowrap;">Days:</span>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$today}&renewal_to={$today7}')"
                style="{$s7}">7 days</a>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$today}&renewal_to={$today15}')"
                style="{$s15}">15 days</a>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$today}&renewal_to={$today30}')"
                style="{$s30}">30 days</a>
        </div>

        <!-- Row 2: Month-based quick filters -->
        <div style="margin-bottom:10px;display:flex;align-items:center;flex-wrap:wrap;gap:2px;">
            <span style="font-size:11px;color:#999;margin-right:4px;white-space:nowrap;">Month:</span>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$thisMonthStart}&renewal_to={$thisMonthEnd}')"
                style="{$sThisMonth}">This month</a>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$nextMonthStart}&renewal_to={$nextMonthEnd}')"
                style="{$sNextMonth}">Next month</a>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$today}&renewal_to={$next3End}')"
                style="{$s3m}">3 months</a>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$today}&renewal_to={$next6End}')"
                style="{$s6m}">6 months</a>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$today}&renewal_to={$next12End}')"
                style="{$s12m}">12 months</a>
        </div>

        <!-- Row 3: Custom date range -->
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:11px;color:#999;white-space:nowrap;">Custom:</span>
            <input type="date" id="rw_from" value="{$fromDate}"
                style="border:1px solid #ddd;border-radius:4px;padding:4px 8px;font-size:12px;color:#333;outline:none;" />
            <span style="font-size:12px;color:#999;">to</span>
            <input type="date" id="rw_to" value="{$toDate}"
                style="border:1px solid #ddd;border-radius:4px;padding:4px 8px;font-size:12px;color:#333;outline:none;" />
            <a onclick="applyRenewalFilter()"
                style="display:inline-block;padding:4px 14px;background:#3498db;color:#fff;
                border-radius:4px;font-size:12px;cursor:pointer;font-weight:600;text-decoration:none;">
                Apply
            </a>
            <a onclick="refreshWidget('UpcomingRenewals','renewal_from={$today}&renewal_to={$today30}')"
                style="display:inline-block;padding:4px 10px;background:#fff;color:#999;
                border:1px solid #ddd;border-radius:4px;font-size:12px;cursor:pointer;text-decoration:none;">
                Reset
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div style="display:flex;gap:10px;margin-bottom:16px;">
        <div style="flex:1;background:#eaf4fb;border-left:4px solid #3498db;
            border-radius:6px;padding:10px 14px;">
            <div style="font-size:22px;font-weight:700;color:#2980b9;">{$count7}</div>
            <div style="font-size:12px;color:#666;margin-top:2px;">Due in 7 days</div>
        </div>
        <div style="flex:1;background:#eafaf1;border-left:4px solid #27ae60;
            border-radius:6px;padding:10px 14px;">
            <div style="font-size:22px;font-weight:700;color:#1e8449;">{$count}</div>
            <div style="font-size:12px;color:#666;margin-top:2px;">In selected range</div>
        </div>
        <div style="flex:1;background:#fef9e7;border-left:4px solid #f39c12;
            border-radius:6px;padding:10px 14px;">
            <div style="font-size:22px;font-weight:700;color:#d68910;">₹{$totalRevenue}</div>
            <div style="font-size:12px;color:#666;margin-top:2px;">Expected revenue</div>
        </div>
    </div>

    <!-- Renewals Table -->
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid #eee;">
                <th style="padding:6px;text-align:left;color:#aaa;font-weight:600;
                    font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Service / Client</th>
                <th style="padding:6px;text-align:left;color:#aaa;font-weight:600;
                    font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Due Date</th>
                <th style="padding:6px;text-align:center;color:#aaa;font-weight:600;
                    font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Days Left</th>
                <th style="padding:6px;text-align:right;color:#aaa;font-weight:600;
                    font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>

    <!-- Footer -->
    <div style="margin-top:12px;text-align:right;">
        <a href="clientsservices.php?status=Active"
            style="font-size:12px;color:#3498db;text-decoration:none;">
            View all active services →
        </a>
    </div>

</div>

<script>
function applyRenewalFilter() {
    var from = document.getElementById('rw_from').value;
    var to   = document.getElementById('rw_to').value;
    if (!from || !to) {
        alert('Please select both From and To dates.');
        return;
    }
    if (from > to) {
        alert('From date cannot be after To date.');
        return;
    }
    refreshWidget('UpcomingRenewals', 'renewal_from=' + from + '&renewal_to=' + to);
}
</script>
HTML;
    }
}
