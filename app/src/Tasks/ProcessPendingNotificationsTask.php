<?php

namespace App\Tasks;

use App\Calendar\Appointment;
use App\Calendar\SchedulingPoll;
use App\Announcements\Announcement;
use App\Notifications\PendingNotificationJob;
use App\Notifications\PushNotificationService;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class ProcessPendingNotificationsTask extends BuildTask
{
    protected string $title = 'Benachrichtigungen verarbeiten';
    protected static string $description = 'Verarbeitet ausstehende Push-Benachrichtigungen und sendet sie via Firebase.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $jobs = PendingNotificationJob::get()->filter('Status', 'pending')->limit(100);
        $count = $jobs->count();

        foreach ($jobs as $job) {
            // Mitteilungen mit Veröffentlichungsdatum in der Zukunft erst ab diesem Zeitpunkt melden
            if ($this->isDeferred($job)) {
                $count--;
                continue;
            }

            try {
                $this->processJob($job);
                $job->Status = 'done';
            } catch (\Exception $e) {
                $job->Status = 'failed';
                $job->ErrorMessage = $e->getMessage();
                error_log('PendingNotificationJob #' . $job->ID . ' failed: ' . $e->getMessage());
            }

            $job->write();
        }

        $output->writeln('Verarbeitet: ' . $count . ' Job(s).');
        return Command::SUCCESS;
    }

    private function isDeferred(PendingNotificationJob $job): bool
    {
        if ($job->EventType !== 'new_announcement') {
            return false;
        }
        $announcement = Announcement::get()->byID($job->SourceID);
        return $announcement
            && $announcement->ReleaseDate
            && strtotime($announcement->ReleaseDate) > DBDatetime::now()->getTimestamp();
    }

    private function processJob(PendingNotificationJob $job): void
    {
        switch ($job->EventType) {
            case 'new_announcement':
                $announcement = Announcement::get()->byID($job->SourceID);
                if ($announcement) {
                    PushNotificationService::notifyNewAnnouncement($announcement);
                }
                break;

            case 'appointment_suggested':
                $appointment = Appointment::get()->byID($job->SourceID);
                if ($appointment) {
                    PushNotificationService::notifyAppointmentSuggested($appointment);
                }
                break;

            case 'appointment_scheduled':
                $appointment = Appointment::get()->byID($job->SourceID);
                if ($appointment) {
                    PushNotificationService::notifyAppointmentScheduled($appointment);
                }
                break;

            case 'appointment_cancelled':
                $appointment = Appointment::get()->byID($job->SourceID);
                if ($appointment) {
                    PushNotificationService::notifyAppointmentCancelled($appointment);
                }
                break;

            case 'poll_created':
                $poll = SchedulingPoll::get()->byID($job->SourceID);
                if ($poll) {
                    PushNotificationService::notifyPollCreated($poll);
                }
                break;
        }
    }
}
