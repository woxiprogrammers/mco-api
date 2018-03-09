<?php
    /**
     * Created by Harsha.
     * User: Harsha
     * Date: 9/3/18
     * Time: 10:39 AM
     */?>

<html>
<body>
Dear Sir,<br>
<span style="margin-left: 5%"> We have received the following material against the PO {{$purchaseOrder->format_id}} : </span><br>
<table id="itemTable" style="font-size: 12px;">
    <tr style="text-align: center">
        <th style="width: 8px">
            Sr.no.
        </th>
        <th style="width: 150px;">
            Material Name
        </th>
        <th>
            Quantity Requested
        </th>
        <th>
            Quantity Received
        </th>
        <th>
            Unit
        </th>
    </tr>
    @for($iterator = 0; $iterator < count($purchaseOrderComponent) ; $iterator++ )
    <tr style="text-align: center">
        <td>
            {!! $iterator + 1 !!}
        </td>
        <td style="text-align: left;padding-left: 5px" >
            {{$purchaseOrderComponent[$iterator]->purchaseRequestComponent->materialRequestComponent->name}}
        </td>
        <td style="text-align: left;padding-left: 5px" >
            {{$purchaseOrderComponent[$iterator]['quantity']}}
        </td>
        <td>
            {{$purchaseOrderComponent[$iterator]->purchaseOrderTransactionComponent->sum('quantity')}}
        </td>
        <td>
            {{$purchaseOrderComponent[$iterator]->unit->name}}
        </td>
    </tr>
    @endfor
</table>
<br>
<span style="margin-left: 5%"> We hereby close the above mentioned PO.</span><br>
<br>
<span style="margin-left: 5%"> *This is an auto generated message.</span><br>
<span style="margin-left: 5%"> *Please revert back at purchase.manishaconstruction@gmail.com for any queries.</span><br>
<br><br>
<span>Thanking you,</span><br>
<span>Purchase Manager,</span><br>
<span>Manisha Construction,</span><br>
<span>Siddhi Tower, Above Rupee Bank,</span><br>
<span>5th Floor, Opp. Parmar Pavan Bldg,</span><br>
<span>Kondhwa, Pune - 411048</span><br>
<span>Contact No. - 02026831325/26</span><br>
</body>
</html>
