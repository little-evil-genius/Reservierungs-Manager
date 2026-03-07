function hideReservationBanner(rid) {
    let formData = new FormData();
    formData.append('rid', rid);

    fetch('index.php?action=reservationsHidebanner', {
        method: 'POST',
        body: formData
    });

    const elem = document.querySelector('#reservationsBanner-' + rid);
    if(elem) {
        elem.closest('.reservations_banner').style.display = 'none';
    }
}