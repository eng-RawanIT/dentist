<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portfolio</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #333;
            margin: 0;
            padding: 30px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #1D4355;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .header img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1D4355;
        }

        .info-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ccc;
        }

        .stage-section {
            margin-top: 30px;
        }

        .stage-title {
            font-size: 18px;
            color: #1D4355;
            margin-bottom: 5px;
        }

        .evaluation {
            margin-bottom: 10px;
            font-weight: bold;
            color: #1D4355;
        }

        .patients-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .patients-table th,
        .patients-table td {
            border: 1.5px solid #1D4355;
            padding: 8px 10px;
            text-align: left;
        }

        .patients-table th {
            background-color: #1D4355;
            color: #fff;
        }

        .patients-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .patients-table tr:hover {
            background-color: #eef2f5;
        }
    </style>
</head>
<body>

<div class="header">
    @if ($profile_image_url)
        <img src="{{ public_path('storage/' . $profile_image_url) }}" alt="Student Image">
    @endif
    <h1>{{ $name }}'s Portfolio</h1>
    <p><strong>Year:</strong> {{ $year }}</p>
    <p><strong>Overall Grade:</strong> {{ number_format($overall_grade, 2) }}</p>
</div>

@foreach ($stages as $stage)
    <div class="stage-section">
        <div class="stage-title">Stage: {{ $stage['stage_name'] }}</div>
        <div class="evaluation">Average Evaluation: {{ number_format($stage['stage_average_evaluation'], 2) }}</div>

        <table class="patients-table">
            <thead>
            <tr>
                <th>Patient Name</th>
                <th>Number of Sessions</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($stage['patients'] as $patient)
                <tr>
                    <td>{{ $patient['name'] }}</td>
                    <td>{{ $patient['session_count'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach

</body>
</html>
