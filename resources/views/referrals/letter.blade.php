<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Referral {{ $referral['number'] }}</title>
<style>
    @page { margin: 34px 40px; }
    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: #1A1F23;
        font-size: 11.5px;
        line-height: 1.45;
        margin: 0;
    }
    .muted { color: #5C6670; }
    .right { text-align: right; }
    .small { font-size: 10px; }
    .strong { font-weight: 700; color: #1A1F23; }
    .pre { white-space: pre-wrap; }

    .head { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .brand-badge {
        display: inline-block; width: 34px; height: 34px;
        background: #0F2A3D; color: #fff; border-radius: 8px;
        font-size: 18px; font-weight: 700; text-align: center; line-height: 34px;
        margin-right: 8px;
    }
    .facility { font-size: 19px; font-weight: 700; color: #0F2A3D; letter-spacing: 0.3px; }
    .title { font-size: 22px; font-weight: 700; color: #0F2A3D; letter-spacing: 3px; }
    .rule { border-bottom: 3px solid #0F2A3D; height: 0; margin: 14px 0 16px; }

    .label {
        font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px;
        color: #94A0A8; font-weight: 700; margin-bottom: 2px;
    }
    .badge {
        display: inline-block; padding: 3px 11px; border-radius: 11px;
        font-size: 10px; font-weight: 700; letter-spacing: 0.4px;
    }
    .badge-routine { background: #E7EDF1; color: #1B3F57; }
    .badge-urgent { background: #F6E9DA; color: #8A551F; }
    .badge-emergency { background: #F8E1E1; color: #8A1F1F; }

    table.meta { width: 100%; border-collapse: collapse; }
    table.meta td { vertical-align: top; padding: 0 0 8px; }

    .section-title {
        font-size: 11px; font-weight: 700; color: #0F2A3D;
        text-transform: uppercase; letter-spacing: 0.6px;
        margin: 16px 0 4px; border-bottom: 1px solid #E7EDF1; padding-bottom: 3px;
    }
    .box { border: 1px solid #E7EDF1; border-radius: 6px; padding: 8px 10px; background: #F7F9FA; }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid td { padding: 3px 8px 3px 0; vertical-align: top; }

    .sign { width: 100%; border-collapse: collapse; margin-top: 26px; }
    .sign td { width: 50%; vertical-align: bottom; padding-right: 30px; }
    .sign .line { border-top: 1px solid #1A1F23; padding-top: 4px; margin-top: 28px; }

    .slip { margin-top: 26px; border-top: 2px dashed #94A0A8; padding-top: 12px; }
    .slip .field { border-bottom: 1px solid #94A0A8; height: 18px; margin: 8px 0; }

    .footer {
        margin-top: 18px; border-top: 1px solid #E7EDF1;
        padding-top: 8px; color: #94A0A8; font-size: 9.5px;
    }
</style>
</head>
<body>

    <table class="head">
        <tr>
            <td style="width:60%;">
                <span class="brand-badge">{{ mb_substr($facility['name'], 0, 1) }}</span>
                <span class="facility">{{ $facility['name'] }}</span>
                <div class="muted small" style="margin-top:6px;">
                    @if($facility['location']){{ $facility['location'] }}<br>@endif
                    @if($facility['code'])Facility code {{ $facility['code'] }}@endif
                </div>
            </td>
            <td style="width:40%;" class="right">
                <div class="title">REFERRAL</div>
                <div class="muted small" style="margin-top:6px;">
                    <span class="strong">{{ $referral['number'] }}</span><br>
                    {{ $referral['date'] }}
                </div>
                <div style="margin-top:8px;">
                    <span class="badge {{ $referral['urgency_class'] }}">{{ $referral['urgency'] }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="meta">
        <tr>
            <td style="width:50%;">
                <div class="label">To</div>
                <div class="strong" style="font-size:13px;">{{ $referral['destination_facility'] }}</div>
                @if($referral['destination_department'])<div>{{ $referral['destination_department'] }}</div>@endif
                @if($referral['destination_contact'])<div class="muted small">{{ $referral['destination_contact'] }}</div>@endif
            </td>
            <td style="width:50%;">
                <div class="label">Patient</div>
                <div class="strong" style="font-size:13px;">{{ $patient['name'] }}</div>
                <div class="muted small">
                    File No {{ $patient['file_number'] }}
                    &middot; {{ $patient['sex'] }}
                    @if($patient['age']) &middot; {{ $patient['age'] }} @endif
                    @if($patient['dob']) &middot; born {{ $patient['dob'] }} @endif
                </div>
                @if($patient['phone'])<div class="small">Phone {{ $patient['phone'] }}</div>@endif
                @if($patient['address'])<div class="small">{{ $patient['address'] }}</div>@endif
                @if($patient['next_of_kin'])<div class="small muted">Next of kin: {{ $patient['next_of_kin'] }}</div>@endif
                @if($patient['coverage'])<div class="small muted">Coverage: {{ $patient['coverage'] }}</div>@endif
            </td>
        </tr>
    </table>

    <p>Dear Colleague,</p>
    <p>
        I am referring the above-named patient to you
        @if($referral['destination_department']) ({{ $referral['destination_department'] }}) @endif
        for further management. Kindly see and manage as appropriate.
    </p>

    <div class="section-title">Reason for referral</div>
    <div class="box pre">{{ $referral['reason'] }}</div>

    @if($referral['diagnosis'] || count($problems))
        <div class="section-title">Diagnosis</div>
        <div>
            @if($referral['diagnosis'])<div class="strong">{{ $referral['diagnosis'] }}</div>@endif
            @if(count($problems))
                <div class="muted small">Active problems: {{ implode('; ', $problems) }}</div>
            @endif
        </div>
    @endif

    @if($referral['clinical_summary'])
        <div class="section-title">Clinical summary</div>
        <div class="pre">{{ $referral['clinical_summary'] }}</div>
    @endif

    @if($vitals)
        <div class="section-title">Last recorded vital signs <span class="muted" style="font-weight:400; text-transform:none; letter-spacing:0;">({{ $vitals['recorded_at'] }})</span></div>
        <table class="grid">
            <tr>
                @foreach($vitals['readings'] as $r)
                    <td><span class="muted small">{{ $r['label'] }}</span><br><span class="strong">{{ $r['value'] }}</span></td>
                    @if($loop->iteration % 6 === 0 && ! $loop->last)</tr><tr>@endif
                @endforeach
            </tr>
        </table>
    @endif

    @if($referral['treatment_given'])
        <div class="section-title">Treatment given</div>
        <div class="pre">{{ $referral['treatment_given'] }}</div>
    @endif

    <div class="section-title">Allergies</div>
    <div>{{ count($allergies) ? implode('; ', $allergies) : 'No known allergies recorded.' }}</div>

    <table class="sign">
        <tr>
            <td>
                <div class="line">
                    <span class="strong">{{ $referral['referred_by'] ?? 'Referring clinician' }}</span><br>
                    <span class="muted small">Referring clinician, {{ $facility['name'] }}</span>
                </div>
            </td>
            <td>
                <div class="line">
                    <span class="muted small">Signature and stamp &middot; Date</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="slip">
        <div class="section-title" style="margin-top:0;">Counter-referral (to be completed by the receiving facility and returned)</div>
        <div class="muted small">Referral {{ $referral['number'] }} &middot; {{ $patient['name'] }} &middot; File No {{ $patient['file_number'] }}</div>
        <table class="grid">
            <tr>
                <td style="width:50%;"><div class="label">Seen at</div><div class="field"></div></td>
                <td style="width:50%;"><div class="label">Date seen</div><div class="field"></div></td>
            </tr>
            <tr>
                <td colspan="2"><div class="label">Findings and treatment</div><div class="field"></div><div class="field"></div></td>
            </tr>
            <tr>
                <td colspan="2"><div class="label">Advice for continuing care</div><div class="field"></div></td>
            </tr>
            <tr>
                <td><div class="label">Name and designation</div><div class="field"></div></td>
                <td><div class="label">Signature and stamp</div><div class="field"></div></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Generated {{ $generated_at }} by {{ $facility['name'] }}. This letter contains confidential patient information.
    </div>

</body>
</html>
