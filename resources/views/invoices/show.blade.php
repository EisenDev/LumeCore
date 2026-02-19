<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice['id'] }} - LUME AI</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 40px;
            background-color: #f8fafc;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 50px;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 150px;
            color: rgba(99, 102, 241, 0.05);
            font-weight: 900;
            pointer-events: none;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 60px;
            position: relative;
        }

        .logo {
            width: 150px;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h1 {
            margin: 0;
            font-size: 32px;
            color: #6366f1;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        .detail-group h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .detail-group p {
            margin: 0;
            font-weight: 600;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 60px;
        }

        .table th {
            text-align: left;
            background: #f1f5f9;
            padding: 12px 15px;
            font-size: 12px;
            text-transform: uppercase;
            color: #64748b;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .amount-col {
            text-align: right;
        }

        .total-section {
            margin-left: auto;
            width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
        }

        .total-row.grand-total {
            background: #6366f1;
            color: white;
            border-radius: 8px;
            margin-top: 10px;
            font-weight: 700;
            font-size: 18px;
        }

        .footer {
            margin-top: 80px;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .invoice-container {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="watermark">LUME AI</div>

        <div class="header">
            <div class="logo-area">
                <!-- Replace with actual logo URL or base64 -->
                <h2 style="color: #6366f1; margin: 0; font-weight: 900;">LUME AI</h2>
                <p style="font-size: 12px; color: #64748b; margin: 4px 0;">Sovereign Intelligence Platform</p>
            </div>
            <div class="invoice-title">
                <h1>Invoice</h1>
                <p style="color: #64748b; font-size: 14px;">#{{ $invoice['id'] }}</p>
            </div>
        </div>

        <div class="invoice-details">
            <div class="detail-group">
                <h3>Billed To</h3>
                <p>{{ $user->name }}</p>
                <p style="font-weight: 400; color: #64748b;">{{ $user->email }}</p>
            </div>
            <div class="detail-group" style="text-align: right;">
                <h3>Invoice Date</h3>
                <p>{{ $invoice['date'] }}</p>
                <h3 style="margin-top: 20px;">Reference</h3>
                <p>{{ $invoice['reference'] ?? 'LUME-SUB-' . strtoupper(Str::random(6)) }}</p>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Plan</th>
                    <th class="amount-col">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Monthly Subscription</strong>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Forensic scanning & asset management capacity</div>
                    </td>
                    <td>{{ $invoice['plan'] }}</td>
                    <td class="amount-col">${{ number_format($invoice['amount'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-row">
                <span>Subtotal</span>
                <span>${{ number_format($invoice['amount'], 2) }}</span>
            </div>
            <div class="total-row">
                <span>Tax (0%)</span>
                <span>$0.00</span>
            </div>
            <div class="total-row grand-total">
                <span>Total Amount</span>
                <span>${{ number_format($invoice['amount'], 2) }} USD</span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for using LUME AI. For support, please contact help@lume.ai</p>
            <p>&copy; 2026 EisenDev / LumeAI Platform. All rights reserved.</p>
        </div>
    </div>
    <script>
        // Auto-trigger print dialog when page loads
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
