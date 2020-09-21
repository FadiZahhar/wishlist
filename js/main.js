(function($){

    "use strict";

    /*
        1. Add product to wishlist
        2. Display wishlist items in the table
        3. Remove product from the wishlist

    */
    
    
    /*
    Helper Functions
    Before we move to the events part I want to explain our helper functions
    
    
    The first helper function makes the array unique, by removing duplicates, the second one checks if the given value is present in the given array. 
    The next function executes when an item is added to the wishlist and the last one shows items that are in the wishlist.
    
    */
    Array.prototype.unique = function() {
      return this.filter(function (value, index, self) {
        return self.indexOf(value) === index;
      });
    }

    function isInArray(value, array) {return array.indexOf(value) > -1;}

    function onWishlistComplete(target, title){
        setTimeout(function(){
            target
            .removeClass('loading')
            .addClass('active')
            .attr('title',title);
        },800);
    }

    function highlightWishlist(wishlist,title){
        $('.wishlist-toggle').each(function(){
            var $this = $(this);
            var currentProduct = $this.data('product');
            currentProduct = currentProduct.toString();
            if (isInArray(currentProduct,wishlist)) {
                $this.addClass('active').attr('title',title);
            }
        });
    }
    
    
    /*
    
    6. Now let’s gather the required data and create some variables:
    
    */
    var shopName   = opt.shopName+'-wishlist',
        inWishlist = opt.inWishlist,
        restUrl    = opt.restUrl,
        wishlist   = new Array,
        ls         = sessionStorage.getItem(shopName),
        loggedIn   = ($('body').hasClass('logged-in')) ? true : false,
        userData   = '';
        
        
    /*
    6. If User is Logged-in
    Fetch current user data with AJAX
    If success update the wishlist
    Highlight the wishlist items
    Remove the session/local storage
    If fail show error message in the console for the developer
    
    */

    if(loggedIn) {
        // Fetch current user data
        $.ajax({
            type: 'POST',
            url: opt.ajaxUrl,
            data: {
                'action' : 'fetch_user_data',
                'dataType': 'json'
            },
            success:function(data) {
                userData = JSON.parse(data);
                if (typeof(userData['wishlist']) != 'undefined' && userData['wishlist'] != null && userData['wishlist'] != "") {

                    var userWishlist = userData['wishlist'];
                    userWishlist = userWishlist.split(',');

                    if (wishlist.length) {
                        wishlist =  wishlist.concat(userWishlist);

                        $.ajax({
                            type: 'POST',
                            url:opt.ajaxPost,
                            data:{
                                action:'user_wishlist_update',
                                user_id :userData['user_id'],
                                wishlist :wishlist.join(','),
                            }
                        });

                    } else {
                        wishlist =  userWishlist;
                    }

                    wishlist = wishlist.unique();

                    highlightWishlist(wishlist,inWishlist);
                    sessionStorage.removeItem(shopName);

                } else {
                    if (typeof(ls) != 'undefined' && ls != null) {
                        ls = ls.split(',');
                        ls = ls.unique();
                        wishlist = ls;
                    }

                    $.ajax({
                        type: 'POST',
                        url:opt.ajaxPost,
                        data:{
                            action:'user_wishlist_update',
                            user_id :userData['user_id'],
                            wishlist :wishlist.join(','),
                        }
                    })
                    .done(function(response) {
                        highlightWishlist(wishlist,inWishlist);
                        sessionStorage.removeItem(shopName);
                    });
                }
            },
            error: function(){
                console.log('No user data returned');
            }
        });
    } 
    /*
    
    if User is Guest
    Fetch wishlist from the session/local storage
    
    */
    else {
        if (typeof(ls) != 'undefined' && ls != null) {
            ls = ls.split(',');
            ls = ls.unique();
            wishlist = ls;
        }
    }
    
    
    /*
    
    Now let’s add a click event to the wishlist toggle to power the actual functionality. 
    On each toggle click event the animation is triggered and if the user is logged-in the wishlist update action fires with AJAX. 
    If the user is a guest the item is added to the session/local storage. 

    Now if you go to the shop page, refresh the browser, and click on any wishlist toggle you will see it is working!
    
    */
    $('.wishlist-toggle').each(function(){

        var $this = $(this);

        var currentProduct = $this.data('product');

        currentProduct = currentProduct.toString();

        if (!loggedIn && isInArray(currentProduct,wishlist)) {
            $this.addClass('active').attr('title',inWishlist);
        }

        $(this).on('click',function(e){
            e.preventDefault();
            if (!$this.hasClass('active') && !$this.hasClass('loading')) {

                $this.addClass('loading');

                wishlist.push(currentProduct);
                wishlist = wishlist.unique();

                if (loggedIn) {
                    // get user ID
                    if (userData['user_id']) {
                        $.ajax({
                            type: 'POST',
                            url:opt.ajaxPost,
                            data:{
                                action:'user_wishlist_update',
                                user_id :userData['user_id'],
                                wishlist :wishlist.join(','),
                            }
                        })
                        .done(function(response) {
                            onWishlistComplete($this, inWishlist);
                        })
                        .fail(function(data) {
                            alert(opt.error);
                        });
                    }
                } else {

                    sessionStorage.setItem(shopName, wishlist.toString());
                    onWishlistComplete($this, inWishlist);

                }

            }


        });
    });
    
    
    /*
    
    7. List Items in Wishlist Table
    Now it is time to list our wishlist items in the wishlist table we created earlier.

    Add the following code into main.js at the very bottom of our wrapper function:
    */
    setTimeout(function(){

        if (wishlist.length) {

            restUrl += '?include='+wishlist.join(',');
            restUrl += '&per_page='+wishlist.length;

            $.ajax({
                dataType: 'json',
                url:restUrl
            })
            .done(function(response){
                $('.wishlist-table').each(function(){
                    var $this = $(this);
                    $.each(response,function(index,object){
                        $this.append('<tr data-product="'+object.id+'"><td><a class="wishlist-remove" href="#" title="'+opt.removeWishlist+'"></a>'+object.image+'</td><td>'+object.title["rendered"]+'</td><td>'+object.price+'</td><td>'+object.stock+'</td><td><a class="details" href="'+object.link+'">'+opt.buttonText+'</a></td></tr>');
                    });
                });
            })
            .fail(function(response){
                alert(opt.noWishlist);
            })
            .always(function(response){
                $('.wishlist-table').each(function(){
                    $(this).removeClass('loading');
                });
            });

        } else {
            $('.wishlist-table').each(function(){
                $(this).removeClass('loading');
            });
        }

    },1000);
    
    
    /*
    
    8. Removing Items From Wishlist
    We are almost done; only the remove functionality remains. 
    So let’s create that! Add the following code at the very bottom of the wrapper function in the main.js file
    
    */

    $(document).on('click', '.wishlist-remove', function(){

        var $this = $(this);

        $this.closest('table').addClass('loading');

        wishlist = [];

        $this.closest('table').find('tr').each(function(){

            if ($(this).data('product') != $this.closest('tr').data('product')) {

                wishlist.push($(this).data('product'));

                if (loggedIn) {

                    // get user ID
                    if (userData['user_id']) {
                        $.ajax({
                            type: 'POST',
                            url:opt.ajaxPost,
                            data:{
                                action:'user_wishlist_update',
                                user_id :userData['user_id'],
                                wishlist :wishlist.join(','),
                            }
                        })
                        .done(function(response) {
                            $this.closest('table').removeClass('loading');
                            $this.closest('tr').remove();
                        })
                        .fail(function(data) {
                            alert(opt.error);
                        });
                    }
                } else {
                    sessionStorage.setItem(shopName, wishlist.toString());
                    setTimeout(function(){
                        $this.closest('table').removeClass('loading');
                        $this.closest('tr').remove();
                    },500);
                }
            }

        });

    });

})(jQuery);
