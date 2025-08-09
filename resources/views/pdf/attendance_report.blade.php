<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Attendance Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            padding: 30px;
            color: #1D4355;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #1D4355;
        }

        h4 {
            margin: 5px 0;
        }

        .info {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #999;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #1D4355;
            color: #ffffff;
        }

        .present {
            background-color: #d4edda;
            color: #155724;
        }

        .absent {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>

<h2>Attendance Report</h2>

<div class="info">
    <h4>Supervisor: {{ $supervisor_name }}</h4>
    <h4>Stage: {{ $stage_name }}</h4>
    <h4>Schedule: {{ $schedule_name }}</h4>
    <h4>Date: {{ $date }}</h4>
</div>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Student Name</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach($students as $index => $student)
        <tr class="{{ $student['status'] === 'Present' ? 'present' : 'absent' }}">
            <td>{{ $index + 1 }}</td>
            <td>{{ $student['name'] }}</td>
            <td>{{ $student['status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
