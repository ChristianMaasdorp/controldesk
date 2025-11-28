<?php

namespace App\Imports;

use App\Models\Ticket;
use App\Models\Project;
use App\Models\TicketStatus;
use App\Models\TicketType;
use App\Models\TicketPriority;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TicketsImport implements ToModel, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Use column positions instead of header keys so we don't depend on
        // how Laravel Excel normalizes the headings.
        //
        // Expected export order:
        // 0: Project.name
        // 1: Ticket code
        // 2: Ticket ID
        // 3: Name
        // 4: Owner.name
        // 5: Responsible.name
        // 6: Status.name
        // 7: Type.name
        // 8: Priority.name
        // 9: Created At
        // 10: Total Estimation
        // 11: Start Date

        $values = array_values($row);

        $projectName     = isset($values[0]) ? trim($values[0]) : null;
        $ticketCode      = isset($values[1]) ? trim($values[1]) : null; // unused for now
        $ticketId        = isset($values[2]) ? trim($values[2]) : null; // unused for now
        $ticketName      = isset($values[3]) ? trim($values[3]) : null;
        $ownerName       = isset($values[4]) ? trim($values[4]) : null;
        $responsibleName = isset($values[5]) ? trim($values[5]) : null;
        $statusName      = isset($values[6]) ? trim($values[6]) : null;
        $typeName        = isset($values[7]) ? trim($values[7]) : null;
        $priorityName    = isset($values[8]) ? trim($values[8]) : null;
        $createdAt       = isset($values[9]) ? trim($values[9]) : null;

        // Basic required fields – skip the row if we can't resolve these.
        if (! $projectName || ! $ticketName) {
            Log::warning('TicketsImport skipped row: missing project or name', [
                'projectName' => $projectName,
                'ticketName'  => $ticketName,
            ]);
            return null;
        }

        $project = Project::where('name', $projectName)->first();
        if (! $project) {
            Log::warning('TicketsImport skipped row: project not found', [
                'projectName' => $projectName,
            ]);
            return null;
        }

        $owner = $ownerName
            ? User::where('name', $ownerName)->orWhere('email', $ownerName)->first()
            : null;

        $responsible = $responsibleName
            ? User::where('name', $responsibleName)->orWhere('email', $responsibleName)->first()
            : null;

        $status = $statusName
            ? TicketStatus::where('name', $statusName)->first()
            : null;

        $type = $typeName
            ? TicketType::where('name', $typeName)->first()
            : null;

        $priority = $priorityName
            ? TicketPriority::where('name', $priorityName)->first()
            : null;

        return new Ticket([
            'project_id'           => $project->id,
            'name'                 => $ticketName,
            'content'              => 'Imported from Excel / CSV',
            'owner_id'             => $owner?->id,
            'responsible_id'       => $responsible?->id,
            'status_id'            => $status?->id,
            'type_id'              => $type?->id,
            'priority_id'          => $priority?->id,
            'estimation_hours'     => 0,
            'estimation_minutes'   => 0,
            'estimation_start_date'=> $createdAt ? Carbon::parse($createdAt) : null,
        ]);
    }
}
