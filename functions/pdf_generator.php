<?php
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

class CarbonFootprintPDF extends TCPDF {
    private $userData;
    private $reportData;
    
    public function __construct($userData, $reportData) {
        parent::__construct(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $this->userData = $userData;
        $this->reportData = $reportData;
        
        // Set document information
        $this->SetCreator('Carbon Footprint Analyzer');
        $this->SetAuthor('Carbon Footprint Analyzer');
        $this->SetTitle('Carbon Footprint Report');
        $this->SetSubject('Carbon Emissions Analysis');
        
        // Set default header data
        $this->SetHeaderData('', 0, 'Carbon Footprint Report', 
            'Generated on ' . date('d M Y, h:i A'));
        
        // Set header and footer fonts
        $this->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $this->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        // Set default monospaced font
        $this->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $this->SetMargins(15, 27, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(10);
        
        // Set auto page breaks
        $this->SetAutoPageBreak(TRUE, 25);
        
        // Set image scale factor
        $this->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Set font
        $this->SetFont('helvetica', '', 10);
    }
    
    // Page footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, '© ' . date('Y') . ' Carbon Footprint Analyzer', 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
    
    public function generateReport() {
        // Add a page
        $this->AddPage();
        
        // User Information Section
        $this->addUserInfo();
        
        // Summary Section
        $this->addSummary();
        
        // Detailed Breakdown
        $this->addDetailedBreakdown();
        
        // Emissions Trend
        $this->addEmissionsTrend();
        
        // Recommendations
        $this->addRecommendations();
        
        // Footer note
        $this->addFooterNote();
    }
    
    private function addUserInfo() {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(25, 135, 84); // Green color
        $this->Cell(0, 10, 'Personal Carbon Footprint Report', 0, 1, 'C');
        $this->Ln(5);
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        $html = '
        <table border="0" cellpadding="5" style="width: 100%;">
            <tr>
                <td style="width: 25%; font-weight: bold;">Name:</td>
                <td style="width: 75%;">' . htmlspecialchars($this->userData['name']) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Email:</td>
                <td>' . htmlspecialchars($this->userData['email']) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Department:</td>
                <td>' . htmlspecialchars($this->userData['department']) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Report Date:</td>
                <td>' . date('d M Y', strtotime($this->reportData['record_date'])) . '</td>
            </tr>
        </table>';
        
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(5);
    }
    
    private function addSummary() {
        // Section title
        $this->SetFont('helvetica', 'B', 14);
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'Executive Summary', 0, 1, 'L', true);
        $this->Ln(3);
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        $totalEmissions = $this->reportData['total_emissions'];
        $level = $this->getEmissionLevel($totalEmissions);
        $levelColor = $this->getLevelColor($level);
        
        $html = '
        <table border="1" cellpadding="8" style="width: 100%;">
            <tr style="background-color: #f8f9fa;">
                <td style="width: 50%; font-weight: bold;">Total Carbon Emissions</td>
                <td style="width: 50%; text-align: right; font-size: 16px; color: #198754;">
                    ' . number_format($totalEmissions, 2) . ' kg CO<sub>₂</sub>
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Emission Level</td>
                <td style="text-align: right;">
                    <span style="background-color: ' . $levelColor . '; color: white; padding: 5px 10px; border-radius: 3px;">
                        ' . $level . '
                    </span>
                </td>
            </tr>
            <tr style="background-color: #f8f9fa;">
                <td style="font-weight: bold;">Highest Emission Category</td>
                <td style="text-align: right;">' . htmlspecialchars($this->reportData['highest_category']) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Carbon Offset Equivalent</td>
                <td style="text-align: right;">' . $this->getTreeEquivalent($totalEmissions) . ' trees needed for 1 year</td>
            </tr>
        </table>';
        
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(5);
    }
    
    private function addDetailedBreakdown() {
        // Section title
        $this->SetFont('helvetica', 'B', 14);
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'Detailed Emissions Breakdown', 0, 1, 'L', true);
        $this->Ln(3);
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        $html = '
        <table border="1" cellpadding="6" style="width: 100%;">
            <thead>
                <tr style="background-color: #198754; color: white; font-weight: bold;">
                    <th style="width: 10%; text-align: center;">No.</th>
                    <th style="width: 35%;">Category</th>
                    <th style="width: 25%; text-align: center;">Input Value</th>
                    <th style="width: 30%; text-align: right;">Emissions (kg CO<sub>₂</sub>)</th>
                </tr>
            </thead>
            <tbody>';
        
        $count = 1;
        $totalPercentage = $this->reportData['total_emissions'];
        
        foreach ($this->reportData['details'] as $detail) {
            $percentage = ($detail['emissions'] / $totalPercentage) * 100;
            $bgColor = $count % 2 == 0 ? '#f8f9fa' : '#ffffff';
            
            $html .= '
                <tr style="background-color: ' . $bgColor . ';">
                    <td style="text-align: center;">' . $count++ . '</td>
                    <td>' . htmlspecialchars($detail['category']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($detail['input']) . '</td>
                    <td style="text-align: right;">
                        <strong>' . number_format($detail['emissions'], 2) . '</strong>
                        <span style="color: #6c757d; font-size: 9px;">(' . number_format($percentage, 1) . '%)</span>
                    </td>
                </tr>';
        }
        
        $html .= '
                <tr style="background-color: #d1e7dd; font-weight: bold;">
                    <td colspan="3" style="text-align: right;">TOTAL EMISSIONS:</td>
                    <td style="text-align: right; color: #198754; font-size: 12px;">
                        ' . number_format($this->reportData['total_emissions'], 2) . ' kg CO<sub>₂</sub>
                    </td>
                </tr>
            </tbody>
        </table>';
        
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(5);
    }
    
    private function addEmissionsTrend() {
        if (empty($this->reportData['history'])) {
            return;
        }
        
        // Section title
        $this->SetFont('helvetica', 'B', 14);
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'Emissions History (Last 6 Months)', 0, 1, 'L', true);
        $this->Ln(3);
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        $html = '
        <table border="1" cellpadding="6" style="width: 100%;">
            <thead>
                <tr style="background-color: #198754; color: white; font-weight: bold;">
                    <th style="width: 20%; text-align: center;">Month</th>
                    <th style="width: 30%; text-align: right;">Total Emissions</th>
                    <th style="width: 25%; text-align: center;">Level</th>
                    <th style="width: 25%; text-align: center;">Trend</th>
                </tr>
            </thead>
            <tbody>';
        
        $previousEmission = null;
        foreach ($this->reportData['history'] as $index => $record) {
            $bgColor = $index % 2 == 0 ? '#f8f9fa' : '#ffffff';
            $level = $this->getEmissionLevel($record['total']);
            $levelColor = $this->getLevelColor($level);
            
            // Calculate trend
            $trend = '';
            if ($previousEmission !== null) {
                $change = $record['total'] - $previousEmission;
                if ($change > 0) {
                    $trend = '<span style="color: #dc3545;">↑ +' . number_format(abs($change), 2) . '</span>';
                } elseif ($change < 0) {
                    $trend = '<span style="color: #198754;">↓ -' . number_format(abs($change), 2) . '</span>';
                } else {
                    $trend = '<span style="color: #6c757d;">→ No change</span>';
                }
            }
            $previousEmission = $record['total'];
            
            $html .= '
                <tr style="background-color: ' . $bgColor . ';">
                    <td style="text-align: center;">' . $record['month'] . '</td>
                    <td style="text-align: right;"><strong>' . number_format($record['total'], 2) . ' kg CO₂</strong></td>
                    <td style="text-align: center;">
                        <span style="background-color: ' . $levelColor . '; color: white; padding: 3px 8px; border-radius: 3px; font-size: 9px;">
                            ' . $level . '
                        </span>
                    </td>
                    <td style="text-align: center;">' . $trend . '</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>';
        
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(5);
    }
    
    private function addRecommendations() {
        // Section title
        $this->SetFont('helvetica', 'B', 14);
        $this->SetFillColor(25, 135, 84);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'Personalized Recommendations', 0, 1, 'L', true);
        $this->Ln(3);
        
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        $recommendations = $this->generateRecommendations();
        
        $html = '<ul style="line-height: 1.8;">';
        foreach ($recommendations as $rec) {
            $html .= '<li><strong>' . htmlspecialchars($rec['title']) . ':</strong> ' . 
                     htmlspecialchars($rec['description']) . '</li>';
        }
        $html .= '</ul>';
        
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(5);
    }
    
    private function addFooterNote() {
        $this->SetFont('helvetica', 'I', 9);
        $this->SetTextColor(108, 117, 125);
        
        $html = '
        <div style="border-top: 1px solid #dee2e6; padding-top: 10px; margin-top: 20px;">
            <p style="text-align: center; color: #6c757d;">
                <strong>Note:</strong> This report is generated based on your input data and standard emission factors. 
                The calculations are estimates and actual emissions may vary. For questions or concerns, 
                please contact the sustainability team.
            </p>
        </div>';
        
        $this->writeHTML($html, true, false, true, false, '');
    }
    
    private function getEmissionLevel($emissions) {
        if ($emissions < 50) return 'Low';
        if ($emissions < 100) return 'Medium';
        return 'High';
    }
    
    private function getLevelColor($level) {
        switch ($level) {
            case 'Low': return '#198754';
            case 'Medium': return '#ffc107';
            case 'High': return '#dc3545';
            default: return '#6c757d';
        }
    }
    
    private function getTreeEquivalent($emissions) {
        // Average tree absorbs ~22 kg of CO2 per year
        return ceil($emissions / 22);
    }
    
    private function generateRecommendations() {
        $recommendations = [];
        $highestCategory = $this->reportData['highest_category'];
        
        $tips = [
            'Electricity' => [
                ['title' => 'Switch to LED bulbs', 'description' => 'Replace traditional bulbs with LED lights to reduce electricity consumption by up to 75%'],
                ['title' => 'Unplug devices', 'description' => 'Unplug chargers and appliances when not in use to prevent phantom energy consumption'],
                ['title' => 'Use natural light', 'description' => 'Open curtains and blinds during the day to reduce artificial lighting needs']
            ],
            'Fuel/Transportation' => [
                ['title' => 'Carpool or use public transport', 'description' => 'Share rides with colleagues or use public transportation to reduce individual fuel consumption'],
                ['title' => 'Maintain your vehicle', 'description' => 'Regular maintenance improves fuel efficiency and reduces emissions'],
                ['title' => 'Plan your trips', 'description' => 'Combine errands into single trips to minimize unnecessary driving']
            ],
            'Water' => [
                ['title' => 'Fix leaks promptly', 'description' => 'A dripping tap can waste up to 15 liters per day'],
                ['title' => 'Install water-efficient fixtures', 'description' => 'Use low-flow showerheads and faucet aerators'],
                ['title' => 'Reduce shower time', 'description' => 'Shorter showers significantly reduce water and energy consumption']
            ],
            'Waste' => [
                ['title' => 'Separate recyclables', 'description' => 'Properly sort waste to increase recycling rates'],
                ['title' => 'Reduce single-use plastics', 'description' => 'Use reusable bags, bottles, and containers'],
                ['title' => 'Compost organic waste', 'description' => 'Composting reduces methane emissions from landfills']
            ],
            'Paper' => [
                ['title' => 'Go digital', 'description' => 'Use digital documents and signatures instead of printing'],
                ['title' => 'Print double-sided', 'description' => 'When printing is necessary, use both sides of the paper'],
                ['title' => 'Use recycled paper', 'description' => 'Choose recycled paper products with high post-consumer content']
            ],
            'Food Choices' => [
                ['title' => 'Reduce meat consumption', 'description' => 'Consider meat-free days to lower your carbon footprint'],
                ['title' => 'Buy local and seasonal', 'description' => 'Choose locally produced, seasonal foods to reduce transportation emissions'],
                ['title' => 'Minimize food waste', 'description' => 'Plan meals and store food properly to reduce waste']
            ]
        ];
        
        // Get recommendations for highest category
        if (isset($tips[$highestCategory])) {
            return $tips[$highestCategory];
        }
        
        // Default recommendations
        return [
            ['title' => 'Track regularly', 'description' => 'Continue monitoring your carbon footprint to identify trends'],
            ['title' => 'Set reduction goals', 'description' => 'Aim to reduce emissions by 10-20% over the next quarter'],
            ['title' => 'Educate others', 'description' => 'Share sustainability tips with family and colleagues']
        ];
    }
}

function generateCarbonReport($conn, $recordId, $userId) {
    // Get user data
    $sql = "SELECT name, email, department FROM user WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    
    // Get record data
    $sql = "SELECT record_date, total_carbon_emissions 
            FROM emissions_record 
            WHERE record_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $recordId, $userId);
    $stmt->execute();
    $recordData = $stmt->get_result()->fetch_assoc();
    
    if (!$recordData) {
        return false;
    }
    
    // Get emissions details
    $sql = "SELECT ec.category_name, ed.input_value, ed.emissions_value
            FROM emissions_details ed
            JOIN emissions_category ec ON ed.category_id = ec.category_id
            WHERE ed.record_id = ?
            ORDER BY ed.emissions_value DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $detailsResult = $stmt->get_result();
    
    $details = [];
    $highestCategory = '';
    $highestValue = 0;
    
    while ($row = $detailsResult->fetch_assoc()) {
        $details[] = [
            'category' => $row['category_name'],
            'input' => $row['input_value'],
            'emissions' => $row['emissions_value']
        ];
        
        if ($row['emissions_value'] > $highestValue) {
            $highestValue = $row['emissions_value'];
            $highestCategory = $row['category_name'];
        }
    }
    
    // Get history (last 6 months)
    $sql = "SELECT 
                DATE_FORMAT(record_date, '%b %Y') as month,
                SUM(total_carbon_emissions) as total
            FROM emissions_record
            WHERE user_id = ?
            AND record_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(record_date, '%Y-%m')
            ORDER BY record_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $historyResult = $stmt->get_result();
    
    $history = [];
    while ($row = $historyResult->fetch_assoc()) {
        $history[] = $row;
    }
    
    // Prepare report data
    $reportData = [
        'record_date' => $recordData['record_date'],
        'total_emissions' => $recordData['total_carbon_emissions'],
        'highest_category' => $highestCategory,
        'details' => $details,
        'history' => $history
    ];
    
    // Generate PDF
    $pdf = new CarbonFootprintPDF($userData, $reportData);
    $pdf->generateReport();
    
    // Save PDF
    $filename = 'carbon_report_' . $recordId . '_' . date('Ymd_His') . '.pdf';
    $filepath = __DIR__ . '/../reports/' . $filename;
    
    // Create reports directory if it doesn't exist
    if (!file_exists(__DIR__ . '/../reports/')) {
        mkdir(__DIR__ . '/../reports/', 0755, true);
    }
    
    $pdf->Output($filepath, 'F');
    
    // Save report record to database
    $sql = "INSERT INTO report (record_id, pdf_path) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $relativePath = 'reports/' . $filename;
    $stmt->bind_param("is", $recordId, $relativePath);
    $stmt->execute();
    
    return $relativePath;
}
?>