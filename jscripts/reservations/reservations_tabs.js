function openReservationTab(evt, reservationName) {
    var i, reservationTabcontent, reservationTablinks;

    reservationTabcontent = document.getElementsByClassName("reservationTabcontent");
    for (i = 0; i < reservationTabcontent.length; i++) {
        reservationTabcontent[i].style.display = "none";
    }

    reservationTablinks = document.getElementsByClassName("reservationTablinks");
    for (i = 0; i < reservationTablinks.length; i++) {
        reservationTablinks[i].className = reservationTablinks[i].className.replace(" active", "");
    }

    document.getElementById(reservationName).style.display = "block";
    evt.currentTarget.className += " active";
}

document.addEventListener("DOMContentLoaded", function() {
    var defaultTab = document.getElementById("reservations_defaultTab");
    if (defaultTab) defaultTab.click();
});