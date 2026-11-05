<?php
require_once __DIR__.'/compat.php';

/** Native XLSX export matching the supplied workbook's Tracking Record sheet. */
final class TrackingExcel {
    private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const HEADERS=['Manufacturing Date','Chemical Name','Batch','Tank','Factory','Po','Remark','Testing Date','Upload COA','Status COA','Last Modified By','Last Modified'];
    private const WIDTHS=[16.88,35.13,14.63,16.63,12.5,17.13,46.38,13.0,13.25,18.0,25.0,23.0];

    /** Returns a temporary ZIP file; the caller sends it as .xlsx and deletes it. */
    public static function create(iterable $records): string {
        $sheet=tempnam(sys_get_temp_dir(),'qachem-sheet-');
        $seed=tempnam(sys_get_temp_dir(),'qachem-excel-');
        if($sheet===false||$seed===false){
            if($sheet!==false)unlink($sheet);
            if($seed!==false)unlink($seed);
            throw new RuntimeException('Unable to create the Excel temporary files.');
        }
        $path=$seed.'.zip';unlink($seed);
        try{
            self::worksheet($sheet,$records);
            // PharData writes standard ZIP packages without requiring the optional PHP zip extension.
            $zip=new PharData($path,0,null,Phar::ZIP);
            foreach(self::parts() as $name=>$xml)$zip->addFromString($name,$xml);
            $zip->addFile($sheet,'xl/worksheets/sheet1.xml');
            $zip->compressFiles(Phar::GZ);unset($zip);
            return $path;
        }catch(Throwable $error){
            unset($zip);
            if(is_file($path))unlink($path);
            throw $error;
        }finally{unlink($sheet);}
    }

    private static function values(array $record): array {
        $generated=!empty($record['coa_generated_at']);
        return [
            $record['manufacturing_date']??'', $record['chemical_name']??'', $record['batch_no']??'',
            ($record['packaging_no']??'')?:($record['source_tank']??''), $record['factory']??'',
            $record['po_no']??'', $record['remark']??'', $record['testing_date']??'',
            $generated?($record['upload_date']??''):'',
            $generated?'GENERATED':(!empty($record['planned'])?'DRAFT':'NOT GENERATED'),
            $record['modified_by_name']??$record['created_by_name']??'',
            $record['updated_at']??''
        ];
    }

    private static function worksheet(string $path,iterable $records): void {
        $xml=new XMLWriter();
        if(!$xml->openUri($path))throw new RuntimeException('Unable to write the Excel worksheet.');
        $xml->startDocument('1.0','UTF-8');$xml->startElementNS(null,'worksheet',self::NS);
        $xml->startElement('sheetViews');$xml->startElement('sheetView');$xml->writeAttribute('workbookViewId','0');
        $xml->startElement('pane');
        foreach(['ySplit'=>'1','topLeftCell'=>'A2','activePane'=>'bottomLeft','state'=>'frozen'] as $key=>$value)$xml->writeAttribute($key,$value);
        $xml->endElement();$xml->endElement();$xml->endElement();
        $xml->startElement('sheetFormatPr');$xml->writeAttribute('defaultRowHeight','18');$xml->endElement();
        $xml->startElement('cols');
        foreach(self::WIDTHS as $index=>$width){
            $xml->startElement('col');
            foreach(['min'=>$index+1,'max'=>$index+1,'width'=>$width,'customWidth'=>1] as $key=>$value)$xml->writeAttribute($key,(string)$value);
            $xml->endElement();
        }
        $xml->endElement();$xml->startElement('sheetData');
        self::row($xml,1,self::HEADERS,true);$number=1;
        foreach($records as $record){
            if(++$number>1048576)throw new RuntimeException('The export exceeds the Excel worksheet row limit.');
            self::row($xml,$number,self::values($record));
        }
        $xml->endElement();
        $xml->startElement('autoFilter');$xml->writeAttribute('ref','A1:L'.$number);$xml->endElement();
        $xml->startElement('pageMargins');
        foreach(['left'=>0.25,'right'=>0.25,'top'=>0.5,'bottom'=>0.5,'header'=>0.2,'footer'=>0.2] as $key=>$value)$xml->writeAttribute($key,(string)$value);
        $xml->endElement();
        $xml->startElement('pageSetup');
        foreach(['paperSize'=>'9','orientation'=>'landscape','fitToWidth'=>'1','fitToHeight'=>'0'] as $key=>$value)$xml->writeAttribute($key,$value);
        $xml->endElement();$xml->endElement();$xml->endDocument();$xml->flush();
    }

    private static function row(XMLWriter $xml,int $number,array $values,bool $header=false): void {
        $lines=1;
        foreach($values as $index=>$value){
            $length=max(1,(int)floor(self::WIDTHS[$index]-2));
            $wrapped=0;
            foreach(explode("\n",(string)$value) as $line)$wrapped+=max(1,(int)ceil(qc_text_width($line)/$length));
            $lines=max($lines,$wrapped);
        }
        $xml->startElement('row');$xml->writeAttribute('r',(string)$number);
        $xml->writeAttribute('ht',(string)($header?30:min(409, max(21,$lines*14+6))));$xml->writeAttribute('customHeight','1');
        foreach($values as $index=>$value){
            $text=(string)$value;
            // Excel's XML must not contain control characters from user-entered remarks.
            $text=preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$text)??'';
            $text=qc_text_slice($text,0,32767);
            $date=!$header&&in_array($index,[0,7,8],true)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$text);
            $xml->startElement('c');$xml->writeAttribute('r',chr(65+$index).$number);
            $xml->writeAttribute('s',$header?'1':($date?'3':'2'));
            if($date){
                $day=new DateTimeImmutable($text,new DateTimeZone('UTC'));
                $base=new DateTimeImmutable('1899-12-30',new DateTimeZone('UTC'));
                $xml->writeElement('v',(string)$base->diff($day)->format('%r%a'));
            }else{
                // Explicit strings preserve leading zeroes and prevent formulas in batch/PO/remarks.
                $xml->writeAttribute('t','inlineStr');$xml->startElement('is');$xml->startElement('t');
                $xml->writeAttribute('xml:space','preserve');$xml->text($text);
                $xml->endElement();$xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
    }

    private static function parts(): array {
        $declaration='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        return [
            '[Content_Types].xml'=>$declaration.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'=>$declaration.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>$declaration.'<workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="Tracking Record" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\'Tracking Record\'!$1:$1</definedName></definedNames></workbook>',
            'xl/_rels/workbook.xml.rels'=>$declaration.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>$declaration.'<styleSheet xmlns="'.self::NS.'"><numFmts count="1"><numFmt numFmtId="164" formatCode="dd/MM/yyyy"/></numFmts><fonts count="1"><font><sz val="10"/><color rgb="FF000000"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFC9DAF8"/><bgColor rgb="FFC9DAF8"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>'
        ];
    }
}
