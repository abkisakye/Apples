* { box-sizing: border-box; }
body {
    margin: 0;
    background: #ffffff;
    color: #202124;
    font-family: "Trebuchet MS", "Segoe UI", sans-serif;
    font-size: 13px;
}
.page {
    max-width: {{ $pageWidth ?? '920px' }};
    margin: 0 auto;
    background: #ffffff;
    padding: 26px 28px 30px;
    border: 1px solid #e3dfd1;
    box-shadow: 0 16px 34px rgba(47, 38, 22, .07);
}
.toolbar {
    padding: 16px 0 8px;
    max-width: {{ $pageWidth ?? '920px' }};
    margin: 0 auto;
}
.toolbar button {
    border: 0;
    border-radius: 12px;
    padding: 10px 18px;
    background: linear-gradient(135deg, #d4af37, #ba9324);
    color: #4f3b04;
    cursor: pointer;
    font-weight: 700;
}
.header {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 3px solid #066838;
    margin-bottom: 16px;
}
.header td {
    vertical-align: top;
    padding: 0 0 12px;
    border: 0;
}
.brand-block {
    padding-right: 18px;
}
.brand-row {
    width: 100%;
    border-collapse: collapse;
}
.brand-row td {
    border: 0;
    padding: 0;
    vertical-align: middle;
}
.brand-icon-wrap {
    width: 58px;
    padding-right: 10px;
}
.brand-icon {
    width: 48px;
    height: 48px;
    display: block;
}
.brand-name {
    font-size: 25px;
    line-height: 1.05;
    font-weight: 800;
    color: #066838;
    margin: 0 0 2px;
}
.brand-tagline {
    font-size: 12px;
    color: #7a6e46;
    margin: 0;
}
.brand-meta {
    margin-top: 8px;
    color: #555555;
    font-size: 12px;
    line-height: 1.5;
}
.doc-block {
    width: 260px;
    text-align: right;
}
.doc-label {
    font-size: 11px;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #8a6f1e;
    font-weight: 700;
    margin-bottom: 5px;
}
.doc-name {
    font-size: 24px;
    line-height: 1.1;
    font-weight: 800;
    color: #1f1f1f;
    margin-bottom: 6px;
}
.doc-meta {
    color: #555555;
    font-size: 12px;
    line-height: 1.55;
}
.overview {
    width: 100%;
    border-collapse: separate;
    border-spacing: 10px 0;
    margin: 0 -10px 16px;
}
.overview td {
    width: 33.33%;
    border: 1px solid #dfdac7;
    background: #ffffff;
    padding: 12px 14px;
    vertical-align: top;
}
.overview.overview-4 td {
    width: 25%;
}
.overview.overview-2 td {
    width: 50%;
}
.section-kicker,
.panel-title {
    display: block;
    margin-bottom: 6px;
    color: #066838;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
}
.metric-value {
    font-size: 17px;
    line-height: 1.2;
    font-weight: 800;
    color: #1f1f1f;
}
.note-copy,
.detail-copy,
.profile-line {
    color: #333333;
    line-height: 1.45;
}
.content {
    width: 100%;
    border-collapse: separate;
    border-spacing: 12px 0;
    margin: 0 -12px 16px;
}
.content td {
    width: 50%;
    padding: 0;
    vertical-align: top;
    border: 0;
}
.content.content-3 td {
    width: 33.33%;
}
.content.content-1 td {
    width: 100%;
}
.panel {
    border: 1px solid #dfdac7;
    background: #ffffff;
    padding: 14px 16px;
    min-height: 118px;
}
.profile-name {
    font-size: 18px;
    font-weight: 800;
    color: #1f1f1f;
    margin-bottom: 6px;
}
.summary-table {
    width: 100%;
    border-collapse: collapse;
}
.summary-table td {
    border: 0;
    border-bottom: 1px solid #ece7d8;
    padding: 7px 0;
    vertical-align: top;
}
.summary-table tr:last-child td {
    border-bottom: 0;
}
.summary-table td:first-child {
    color: #555555;
    padding-right: 12px;
}
.summary-table td:last-child {
    text-align: right;
    font-weight: 800;
    color: #1f1f1f;
    white-space: nowrap;
}
.ledger {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6px;
}
.ledger th,
.ledger td {
    border: 1px solid #ece7d8;
    padding: 8px 7px;
    text-align: left;
    vertical-align: top;
}
.ledger th {
    background: #066838;
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
}
.ledger tbody tr:nth-child(even) td {
    background: #faf8f2;
}
.money,
.qty {
    text-align: right;
    white-space: nowrap;
}
.footer-note {
    margin-top: 16px;
    padding-top: 10px;
    border-top: 1px dashed #cdbb7d;
    color: #555555;
    font-size: 12px;
    line-height: 1.45;
}
.signature-row {
    width: 100%;
    border-collapse: separate;
    border-spacing: 16px 0;
    margin: 22px -16px 0;
}
.signature-row td {
    width: 50%;
    border-top: 1px solid #cdbb7d;
    padding-top: 10px;
    color: #444444;
}
@media print {
    @page { size: A4; margin: 10mm; }
    body { background: #ffffff; }
    .toolbar { display: none; }
    .page {
        max-width: none;
        padding: 0;
        border: 0;
        box-shadow: none;
    }
}
@media (max-width: 720px) {
    .header,
    .overview,
    .content,
    .signature-row {
        border-spacing: 0;
        margin: 0;
    }
    .header,
    .overview,
    .content,
    .signature-row,
    .header tbody,
    .overview tbody,
    .content tbody,
    .signature-row tbody,
    .header tr,
    .overview tr,
    .content tr,
    .signature-row tr,
    .header td,
    .overview td,
    .content td,
    .signature-row td {
        display: block;
        width: 100%;
    }
    .doc-block {
        text-align: left;
        margin-top: 14px;
    }
    .overview td,
    .content td,
    .signature-row td {
        margin-bottom: 12px;
    }
}
