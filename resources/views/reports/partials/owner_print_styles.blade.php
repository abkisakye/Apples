<style>
    .owner-report {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 18px;
    }
    .owner-report-head {
        text-align: center;
        margin-bottom: 14px;
    }
    .owner-report-head h2,
    .owner-report-head h3,
    .owner-report-head p {
        margin: 0;
    }
    .owner-report-head h2 {
        font-size: 1.16rem;
        letter-spacing: .06em;
    }
    .owner-report-head h3 {
        margin-top: 5px;
        font-size: 1rem;
    }
    .owner-report-meta {
        margin-top: 8px;
        color: var(--muted);
        font-size: .86rem;
    }
    .owner-report-section {
        margin-top: 14px;
        border: 1px solid var(--line);
        border-radius: 10px;
        overflow: hidden;
    }
    .owner-report-section-title {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 10px;
        background: #fbf8ef;
        font-weight: 800;
    }
    .owner-report table {
        width: 100%;
        border-collapse: collapse;
        font-size: .9rem;
    }
    .owner-report th,
    .owner-report td {
        border-bottom: 1px solid var(--line);
        padding: 7px 8px;
        vertical-align: top;
    }
    .owner-report th {
        background: #f7f2e6;
        text-align: left;
        font-size: .74rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .owner-total-row {
        background: #fbf8ef;
        font-weight: 800;
    }
    .owner-grand-total {
        display: flex;
        justify-content: flex-end;
        gap: 14px;
        margin-top: 14px;
        padding: 10px 12px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: #f7f2e6;
        font-weight: 900;
    }
    .owner-report-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }
    @media print {
        .sidebar,
        .topbar,
        .page-head,
        .filters,
        .owner-report-actions,
        .developer-credit.screen-only {
            display: none !important;
        }
        .shell {
            display: block;
        }
        .workspace,
        .page {
            padding: 0;
            max-width: none;
        }
        body {
            background: #fff;
        }
        .owner-report {
            border: 0;
            border-radius: 0;
            padding: 0;
        }
        .owner-report th,
        .owner-report td {
            border: 1px solid #222;
            color: #111;
        }
        .owner-report th {
            background: #f2f2f2 !important;
        }
        a {
            color: #111;
            text-decoration: none;
        }
    }
</style>
