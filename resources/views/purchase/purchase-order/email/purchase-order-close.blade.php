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
&nbsp &nbsp &nbsp &nbsp &nbsp We have received the following material against the PO {{$purchaseOrder->format_id}} : <br>
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
&nbsp &nbsp &nbsp &nbsp &nbsp We hereby close the above mentioned PO.<br>
<br>
&nbsp &nbsp &nbsp &nbsp &nbsp *This is an auto generated message.<br>
&nbsp &nbsp &nbsp &nbsp &nbsp *Please revert back at purchase.manishaconstruction@gmail.com for any queries.<br>
<br>
Thanking you,
Purchase Manager,
Manisha Construction
Siddhi Towers, Above Rupee Bank,
5th floor, Opp. Parmar Pavan Bldg,
Kondhwa, Pune - 411048
Contact No:- 020 26831325/26
</body>
</html>
