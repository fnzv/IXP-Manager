<script>
    $( document ).ready( function() {
        loadDataTable();
    });

    /**
     * on click even allow to add a mac address using prompt popup
     */
    $( "#add-l2a" ).on( 'click', function( e ) {
        e.preventDefault();
        bootbox.prompt({
            title: "Enter a MAC Address.",
            inputType: 'text',
            callback: function ( result ) {
                if( result != '' ) {
                    $.ajax( "<?= url( 'api/v4/l2-address/add' ) ?>", {
                        type: 'POST',
                        data: {
                            vliid : <?= $t->vli->getId() ?>,
                            mac : result,
                            _token : "<?= csrf_token() ?>"
                        }
                    })
                    .done( function( data ) {
                        $('.bootbox.modal').modal( 'hide' );
                        result = ( data.success ) ? 'success': 'danger';
                        if( result ) {
                            refreshDataTable();
                        }

                        $( "#message" ).html( "<div class='alert alert-"+result+"' role='alert'>"+ data.message +"</div>" );
                    })
                    .fail( function() {
                        $('.bootbox.modal').modal( 'hide' );
                        $( "#message" ).html( "<div class='alert alert-danger' role='alert'>" +
                            "Could add MAC address. API / AJAX / network error</div>"
                        );
                    });
                }
            }
        });
    });

    /**
     * on click even allow to delete a mac address
     */
    $(document).on('click', "button[id|='delete-l2a']" ,function(e){
        e.preventDefault();
        var l2aId = (this.id).substring(11);
        deleteL2a( l2aId );
    });

    /**
     * allow to refresh the table without reloading the page
     * reloading only a part of the DOM
     */
    function refreshDataTable() {
        $( "#list-area").load( "<?= url('layer2-address/vlan-interface' ).'/'.$t->vli->getId()?> #layer-2-interface-list " ,function( ) {
            table.destroy();
            loadDataTable();
        });
    }

    /**
     * function to delete a mac address using a confirm popup
     */
    function deleteL2a(l2aId){
        bootbox.confirm({
            message: "Do you really want to delete this MAC Address ?",
            buttons: {
                confirm: {
                    label: 'Yes',
                    className: 'btn-primary'
                },
                cancel: {
                    label: 'No',
                    className: 'btn-danger'
                }
            },
            callback: function (result) {
                if( result) {
                    $.ajax( "<?= url( 'api/v4/l2-address/delete' ) ?>/"+l2aId )
                        .done( function( data ) {
                            $('.bootbox.modal').modal( 'hide' );
                            result = ( data.success ) ? 'success': 'danger';
                            if( result ){
                                refreshDataTable();
                            }

                            $( "#message" ).html( "<div class='alert alert-"+result+"' role='alert'>"+ data.message +"</div>" );
                            $( "button[id|='delete-l2a']" ).on('click', deleteL2a);

                        })
                        .fail( function(){
                            alert( 'Could add MAC address. API / AJAX / network error' );
                            throw new Error("Error running ajax query for api/v4/l2-address/{id}/delete");
                        })
                }
            }
        });
    }

    /**
     * initialise the datatable table
     */
    function loadDataTable(){
        table = $( '#layer-2-interface-list' ).DataTable( {
            "autoWidth": false,
            "columnDefs": [{
                "targets": [ 0 ],
                "visible": false,
                "searchable": false,
            }],
            "order": [[ 0, "asc" ]]
        });
    }

</script>