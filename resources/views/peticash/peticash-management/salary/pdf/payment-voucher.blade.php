<html>
    <head>
        <style>
            table {
                border-collapse: collapse;
            }
        </style>
    </head>
    <body>
        <table border="1" id="mainTable">
            <tr style="height: 100px">
                <table style="border-left: 1px solid black; border-right: 1px solid black; border-top: 1px solid black" width="100%">
                    <tr>
                        <td style="width: 10%">
                            <img src="http://mconstruction.co.in/assets/global/img/logo.jpg" height="90px" width="160px">
                        </td>
                        <td style="width: 90%">
                            <table style="padding-top: 2px; padding-bottom:2px;" width="100%">
                                <tr>
                                    <td>
                                        <table width="100%" style="text-align: center; ">
                                            <tr>
                                                <td style="font-size: 22px">
                                                    <i>{!! env('COMPANY_NAME') !!}</i>
                                                </td>
                                                <td style="float: right">
                                                    <b>Date :</b> {!! $date !!}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 16px"><i>{!! env('DESIGNATION') !!}</i></td>
                                                <td style="padding-top: 2px;float: right">
                                                    {{--<b>No. :</b> 12365--}}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 12px"><i>{!! env('ADDRESS') !!}</i></td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 12px">{!! env('CONTACT_NO') !!}</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 12px">{!! env('GSTIN_NUMBER') !!}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </tr>
        </table>

        <table width="100%" style="font-size: 16px;" border="1">
            <tr>
                <td style="font-weight: lighter;" colspan="2"><b>Site :</b> {!! $project_site !!}</td>
            </tr>
            <tr>
                <td style="font-weight: lighter;" colspan="2"><b>Paid to :</b> {!! $paid_to !!}</td>
            </tr>
            <tr>
                <td style="font-weight: lighter;" colspan="2"><b>Amount (in Words) :</b> {!! $amount_in_words !!}</td>
            </tr>
            <tr>
                <td style="font-weight: lighter;" colspan="2"><b>Particulars :</b> {!! $particulars !!}</td>
            </tr>
        </table>

        <table style="font-size:15px" width="100%"{{-- border="1"--}}>
            <tr>
                <td width="10%" style="padding-top:40px"><b> Amount Rs. </b></td>
                <td width="25%" style="padding-top:40px"><div style="height: 30px;width: 100%;border: 1px solid black">{!! $amount !!}</div></td>
                <td width= 30%" style="padding-top:40px;"> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>Approved By</b> {!! $approved_by !!}</td>
                <td width="20%" style="padding-top:40px ;float: right"><b>Receivers Signature</b></td>
            </tr>
        </table>
    </body>
</html>
