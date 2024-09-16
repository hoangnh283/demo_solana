<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <link rel="stylesheet" href="{{ asset('public/css/sol.css') }}">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Create User</h4>
                        <form id="createUserForm1">
                            @csrf
                            <div class="form-group">
                                <label for="name1">Name:</label>
                                <input type="text" id="name1" name="name" class="form-control" autocomplete="off" required>
                            </div>
                            <div class="form-group">
                                <label for="email1">Email:</label>
                                <input type="email" id="email1" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="password1">Password:</label>
                                <input type="password" id="password1" name="password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="password_confirmation1">Confirm Password:</label>
                                <input type="password" id="password_confirmation1" name="password_confirmation" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </form>
                        <div id="message1" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title title-form-3">User List</h4>
                        <ul id="userList" class="list-group">
                            <!-- User list will be populated here -->
                        </ul>
                        <div id="message2" class="mt-3"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">User Balance</h4>
                        <div id="message3" class="mt-3"></div>
                        <div id="userAddress"></div>
                        <div id="balanceInfo" class="mt-3"></div>
                        <div class='buttons d-none'>
                        <button id="depositBtn" class="btn ">Deposit</button>
                        <button id="withdrawBtn" class="btn ">Withdraw</button>
                        <button id="historyBtn" class="btn">History</button>
                        </div>
                        <!-- Deposit Form -->
                        <div id="depositForm" class="d-none mt-3">
                            <h5>Deposit Form</h5>
                            <div id="depositMessage" class="mt-3"></div>
                            <div id="depositQRCode" style="text-align: center;" class="mt-3"></div>
                            <p id="depositNetwork"></p>
                            <p id="depositAddress"></p>

                        </div>

                        <!-- Withdraw Form -->
                        <div id="withdrawForm" class="d-none mt-3">
                            <h5>Withdraw Form</h5>
                            <form id="withdrawFormContent">
                                @csrf
                                <div class="form-group">
                                    <label for="withdrawToken">Token:</label>
                                    <select name="token" id="withdrawToken" required class="form-control"></select>
                                </div>
                                <div class="form-group">
                                    <label for="withdrawAddress">Address:</label>
                                    <input type="text" id="withdrawAddress" name="address" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="withdrawAmount">Amount:</label>
                                    <input type="number" id="withdrawAmount" name="amount" step="any" min="0.00001" class="form-control" >
                                </div>
                                <button type="submit" id="withdrawSubmitBtn" class="btn btn-primary">Submit Withdrawal</button>
                            </form>
                            <div id="withdrawMessage" class="mt-3"></div>
                            <div id="loader" class="d-none" style="text-align: center; margin-top: 20px;">
                                <div class="spinner-border" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                            </div> 
 
                        </div>

                        <!-- History Form -->
                        <div id="historyForm" class="d-none mt-3">
                            <h5>Transaction History</h5>
                            {{-- <button id="fetchHistoryBtn" class="btn btn-secondary">Fetch History</button> --}}
                            <div id="historyTableContainer" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>    
            <!-- Add a modal for showing transaction details -->
            {{-- <div id="transactionModal" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Transaction Details</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Type:</strong> <span id="modalType"></span></p>
                            <p><strong>Amount:</strong> <span id="modalAmount"></span> SOL</p>
                            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                            <p><strong>Time:</strong> <span id="modalTime"></span></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>   --}}
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let current_user = {};
            // Submit form 1
            $('#createUserForm1').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'create-user',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            $('#message1').html('<div class="alert alert-success">' + response.success + '</div>');
                            fetchUserList(); // Update user list after creating a user
                        }
                        if (response.errors) {
                            var errorsHtml = '<div class="alert alert-danger"><ul>';
                            $.each(response.errors, function(key, value) {
                                errorsHtml += '<li>' + value[0] + '</li>';
                            });
                            errorsHtml += '</ul></div>';
                            $('#message1').html(errorsHtml);
                        }
                    },
                    error: function(xhr) {
                        $('#message1').html('<div class="alert alert-danger">An error occurred: ' + xhr.statusText + '</div>');
                    }
                });
            });

            // Fetch user list
            function fetchUserList() {
                $.ajax({
                    url: 'users',
                    type: 'GET',
                    success: function(response) {
                        var userListHtml = '';
                        $.each(response.users, function(index, user) {
                            console.log("🚀 ~ $.each ~ user:", user)
                            var address = user.wallet_address ? user.wallet_address.address : 'N/A';
                            userListHtml += '<li class="list-group-item user-item" data-id="' + user.id + '" data-address="' + address + '"><span>User: ' +
                                user.name + 
                                '</span><br><small class="text-muted"><strong>Address:</strong> ' + address + '</small>' +
                                '</li>';
                        });
                        $('#userList').html(userListHtml);
                    },
                    error: function(xhr) {
                        $('#message2').html('<div class="alert alert-danger">An error occurred: ' + xhr.statusText + '</div>');
                    }
                });
            }

            fetchUserList(); // Fetch user list on page load

            // Handle user click
            $(document).on('click', '.user-item', function() {
                var userId = $(this).data('id');
                var address = current_user.address = $(this).data('address');
                current_user.name = $(this).find('span').text();
                // Remove active class from all user items
                $('.user-item').removeClass('active-user');
                
                // Add active class to the clicked user item
                $(this).addClass('active-user');
                if (!address || address === 'No address') {
                    $('#message3').html('<div class="alert alert-warning">No address available for this user.</div>');
                    return;
                }
                getBalance(userId, address);
                $('.buttons').removeClass('d-none')
                $('#withdrawForm').hide();
                $('#depositForm').hide();
                $('#historyForm').hide();
            });

            function getBalance(user_id, address) {
                $.ajax({
                    url: 'get-balance',
                    type: 'POST',
                    data: { user_id: user_id, address: address, _token: $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if(Object.keys(response).length !== 0){
                            current_user.token = []
                            let html = '<div class="alert alert-info">Balance: '
                            response.forEach((value) => {
                                current_user.token.push(value)
                                html += '<div>'+ value['name']+ ' ' + value['balance'] +'</div>';
                            });
                                console.log("🚀 ~ Object.keys ~ current_user:", current_user)
                            html += '</div>';
                            $('#message3').html(html);
                            document.getElementById('withdrawToken').innerHTML = current_user.token.map(token => `<option value="${token.name}" data-decimals="${token.decimals}">${token.name}</option>`).join('');

                        }else{
                            $('#message3').html('<div class="alert alert-danger">Unable to retrieve balance</div>');
                        }
                    },
                    error: function(xhr) {
                        $('#message3').html('<div class="alert alert-danger">An error occurred: ' + xhr.statusText + '</div>');
                    }
                });
            }
            // Show deposit form
            $('#depositBtn').on('click', function() {
                $('#depositForm').removeClass('d-none').show();
                if(Object.keys(current_user).length !== 0){
                    $('#depositAddress').html('<strong>Address: </strong>' + current_user.address);
                    $('#depositNetwork').html('<strong>Network: </strong>Solana');
                    QRCode.toDataURL(current_user.address, function (err, url) {
                        if (err) {
                            console.error(err);
                            $('#depositQRCode').html('<div class="alert alert-danger">QR error</div>');
                            return;
                        }
                        $('#depositQRCode').html('<img src="' + url + '" alt="QR Code">');
                    });
                }else{
                    $('#depositQRCode').html('<div class="alert alert-danger">Please select user first</div>');
                }
                
                $('#withdrawForm').hide();
                $('#historyForm').hide();
            });

            // Show withdraw form
            $('#withdrawBtn').on('click', function() {
                $('#withdrawAddress').val('')
                $('#withdrawAmount').val('')
                $('#depositMessage').html('')
                $('#withdrawForm').removeClass('d-none').show();
                $('#depositForm').hide();
                $('#historyForm').hide();
            });

            // Show history form
            $('#historyBtn').on('click', function() {
                $('#historyForm').removeClass('d-none').show();
                $('#depositForm').hide();
                $('#withdrawForm').hide(); // Hide withdraw form
            });

            // Handle deposit form submission
            $('#depositFormContent').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'deposit',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            $('#depositMessage').html('<div class="alert alert-success">' + response.success + '</div>');
                            
                        } else if (response.errors) {
                            var errorsHtml = '<div class="alert alert-danger"><ul>';
                            $.each(response.errors, function(key, value) {
                                errorsHtml += '<li>' + value[0] + '</li>';
                            });
                            errorsHtml += '</ul></div>';
                            $('#depositMessage').html(errorsHtml);
                        }
                    },
                    error: function(xhr) {
                        $('#depositMessage').html('<div class="alert alert-danger">An error occurred: ' + xhr.statusText + '</div>');
                    }
                });
            });

            // Handle withdraw form submission
            $('#withdrawFormContent').on('submit', function(e) {
                e.preventDefault();
                var address = $('#withdrawAddress').val().trim();
                var amount = $('#withdrawAmount').val().trim();
                var token = $('#withdrawToken').val().trim();
                var errorMessage = '';
                const decimals = document.getElementById('withdrawToken').selectedOptions[0].dataset.decimals;

                // Validate address
                if (address.length !== 44) {
                    errorMessage += '<div class="alert alert-danger">Wallet address length is 44 characters.</div>';
                } else if (!/^[1-9A-HJ-NP-Za-km-z]+$/.test(address)) {
                    errorMessage += '<div class="alert alert-danger">Wallet address contains invalid characters.</div>';
                }

                // Validate amount
                if (!$.isNumeric(amount) || parseFloat(amount) < 0.00001) {
                    errorMessage += '<div class="alert alert-danger">Amount must be at least 0.00001.</div>';
                }

                if (errorMessage) {
                    $('#withdrawMessage').html(errorMessage);
                    return;
                }else{
                    $('#withdrawMessage').html('')
                }
                $('#withdrawSubmitBtn').attr('disabled', true);
                $('#loader').removeClass('d-none');
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    url: 'withdraw',
                    type: 'POST',
                    data: $(this).serialize(),
                    data: {
                        address: address,
                        amount: amount,
                        token: token,
                        decimals: decimals,
                    },
                    success: function(response) {
                        $('#loader').addClass('d-none');
                        if (response.success) {
                            $('#withdrawMessage').html('<div class="alert alert-success">Withdraw success</div>');
                            setTimeout(() => {
                                getBalance(response.user_id, current_user.address);
                            }, 1000);
                            
                        } else if (response.errors) {
                            var errorsHtml = '<div class="alert alert-danger"><ul>';
                            $.each(response.errors, function(key, value) {
                                errorsHtml += '<li>' + value[0] + '</li>';
                            });
                            errorsHtml += '</ul></div>';
                            $('#withdrawMessage').html(errorsHtml);
                        }
                        $('#withdrawSubmitBtn').attr('disabled', false);
                    },
                    error: function(xhr) {
                        $('#loader').addClass('d-none');
                        $('#withdrawSubmitBtn').attr('disabled', false);
                        $('#withdrawMessage').html('<div class="alert alert-danger">An error occurred: ' + xhr.statusText + '</div>');
                    }
                });
            });

            // Handle fetch history
            $('#historyBtn').on('click', function() {
                $.ajax({
                    url: 'history',
                    type: 'POST',
                    data: { address: current_user.address, _token: $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        // var historyHtml = '<table class="table table-striped" id="historyTable"><thead><tr><th>Type</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead><tbody>';
                        // $.each(response.history, function(index, transaction) {
                        //     historyHtml += '<tr><td>' + transaction.type + '</td><td>' + transaction.amount + ' SOL</td><td>' + transaction.status + '</td><td>' + transaction.block_time + '</tr>';
                        // });
                        var historyHtml = '<table class="table table-striped" id="historyTable">'
                                + '<thead><tr><th>Type</th><th>Network</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead>'
                                + '<tbody>';
                        $.each(response.history, function(index, transaction) {
                            // .toString().replace('.', ',')
                            var amount = transaction.amount
                            var fee = parseFloat(Number(transaction.fee).toFixed(8))
                            historyHtml += '<tr data-toggle="collapse" data-target="#collapse' + index + '">'
                                        + '<td>' + transaction.type + '</td>'
                                        + '<td> Solana </td>'
                                        + '<td>' + amount + '</td>'
                                        + '<td>' + transaction.status + '</td>'
                                        + '<td>' + formatDatetime(transaction.block_time) + '</td>'
                                        + '</tr>'
                                        + '<tr id="collapse' + index + '" class="collapse">'
                                        + '<td colspan="5">'
                                        + '<div><strong>Signature:</strong> ' + transaction.signatures + '</div>'
                                        + '<div><strong>From Address:</strong> ' + transaction.from_address + '</div>'
                                        + '<div><strong>Type:</strong> ' + transaction.type + '</div>'
                                        + '<div><strong>To Address:</strong> ' + transaction.to_address + '</div>'
                                        + '<div><strong>Amount:</strong> ' + amount + '</div>'
                                        + '<div><strong>Fee:</strong> ' + fee + ' SOL</div>'
                                        + '<div><strong>Network:</strong> Solana </div>'
                                        + '<div><strong>Status:</strong> ' + transaction.status + '</div>'
                                        + '<div><strong>Time:</strong> ' + formatDatetime(transaction.block_time) + '</div>'
                                        + '</td>'
                                        + '</tr>';
                                    });
                        historyHtml += '</tbody></table>';
                        $('#historyTableContainer').html(historyHtml);
                    },
                    error: function(xhr) {
                        $('#historyTableContainer').html('<div class="alert alert-danger">An error occurred: ' + xhr.statusText + '</div>');
                    }
                });

            });

            function formatDatetime(originalDate){
                const [datePart, timePart] = originalDate.split(' ');
                const [year, month, day] = datePart.split('-');
                return `${timePart} ${day}/${month}/${year}`
            }
        });
    </script>
</body>
</html>
