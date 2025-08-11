<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Yearly Students Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; padding: 30px; }
        h2, h3 { text-align: center; color: #1D4355FF; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #999; padding: 8px; text-align: center; }
        .reinternship { background-color: #ffeeba; }
    </style>
</head>
<body>

<h2>Students Report - Year {{ $year }}</h2>
<h3>Supervisor: {{ $supervisor_name }}</h3>
<h3>Stage: {{ $stage_name }}</h3>
<h3>Schedule: {{ $schedule_name }}</h3>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Student Name</th>
        <th>Academic Year</th>
        <th>Group Number</th>
        <th>Total Score</th>
        <th>Absence Count</th> <!-- عمود الغياب الجديد -->
    </tr>
    </thead>
    <tbody>
    @foreach($students as $index => $student)
        <tr class="{{ $student['is_reinternship'] ? 'reinternship' : '' }}">
            <td>{{ $index + 1 }}</td>
            <td>{{ $student['name'] }}</td>
            <td>{{ $student['academic_year'] }}</td>
            <td>{{ $student['group_number'] }}</td>
            <td>{{ $student['score'] }}</td>
            <td>{{ $student['absence_count'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
