<div id="midtrans-button-container"></div>

@script
    <script type="text/javascript">
        const script = document.createElement("script");
        script.src = "https://app{{ $debugMode ? '.sandbox' : '' }}.midtrans.com/snap/snap.js";
        script.setAttribute("data-client-key", "{{ $clientKey }}");
        script.async = true;
        document.body.appendChild(script);

        script.onload = () => {
            window.snap.pay("{{ $snapToken }}", {
                onSuccess: function(result){
                    console.log("Payment Success:", result);
                    window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true";
                },
                onPending: function(result){
                    console.log("Payment Pending:", result);
                    window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true";
                },
                onError: function(result){
                    console.error("Payment Failed:", result);
                    alert("Payment failed. Please try again.");
                },
                onClose: function(){
                    console.warn("Payment popup closed without completing.");
                }
            });
        };
    </script>
@endscript
