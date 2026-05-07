<?php

namespace App\Tasks;

use App\Calendar\Appointment;
use App\Announcements\Announcement;
use App\Notifications\PendingNotificationJob;
use App\Notifications\PushNotificationService;
use SilverStripe\Dev\BuildTask;
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
        }
    }
}
