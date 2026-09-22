<div class="container">
    <div class="card p-4 col-12 col-md-11">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-n3 lead font-weight-bold text-uppercase">Data Customer</h6>
                </div>
            </div>
        </div>
        <div class="card-body pb-0 p-0 mt-3">
            <!-- Form input data customer baru -->
            <form method="POST">
                <div class="form-group mt-3">
                    <h6 class="mb-0 mx-1 text-xl font-weight-bold text-uppercase">Data Personal</h6>
                </div>
                <div class="form-group">
                    <label for="nama">Nama Pelanggan</label>
                    <input type="text" class="form-control border p-2" id="nama" name="nama_pelanggan" placeholder="Masukkan nama pelanggan" required>
                </div>
                <div class="form-row d-flex flex-row align-items-center justify-content-between mt-2">
                    <div class="form-group mt-2 w-50">
                        <label for="noWhatsApp">No WhatsApp</label>
                        <div class="d-flex flex-row">
                            <div class="">
                                <span class="input-group-text bg-gradient-info px-2 text-white" style="border-radius: 7px; border-bottom-right-radius:0; border-top-right-radius: 0;">+62</span>
                            </div>
                            <input type="text" class="form-control border p-2" style="border-radius: 7px; border-bottom-left-radius:0; border-top-left-radius: 0;" id="noWhatsApp" name="no_whatsapp" placeholder="Masukkan No WhatsApp" required>
                        </div>
                    </div>
                    <!--<div class="form-group mt-2 w-50 ms-4">-->
                    <!--    <label for="noTelp">No Telepon</label>-->
                    <!--    <div class="d-flex flex-row">-->
                    <!--        <div class="">-->
                    <!--            <span class="input-group-text bg-gradient-info px-2 text-white" style="border-radius: 7px; border-bottom-right-radius:0; border-top-right-radius: 0;">+62</span>-->
                    <!--        </div>-->
                    <!--        <input type="text" class="form-control border p-2" style="border-radius: 7px; border-bottom-left-radius:0; border-top-left-radius: 0;" id="noTelp" name="no_telepon" placeholder="Masukkan No Telepon">-->
                    <!--    </div>-->
                    <!--</div>-->
                </div>

                <div class="form-group mt-2">
                    <label for="email">Email</label>
                    <input type="email" class="form-control border p-2" id="email" name="email_pelanggan" placeholder="Email">
                </div>

                <div class="form-group mt-2">
                    <label for="alamat">Alamat</label>
                    <textarea class="form-control border p-2" id="alamat" rows="3" name="alamat_pelanggan" placeholder="Masukkan alamat"></textarea>
                </div>

                <div class="form-group mt-2">
                    <label for="kota">Kota</label>
                    <input type="text" class="form-control border p-2" id="kota" name="kota_pelanggan" placeholder="Kota">
                </div>

                <div class="form-group mt-2">
                    <label for="kategori">Kategori</label>
                    <select class="form-control border p-2" id="kategori" name="kategori_pelanggan">
                        <option value="dealer">Dealer</option>
                        <option value="installer">Installer</option>
                    </select>
                </div>

                <div class="form-group mt-5">
                    <h6 class="mb-0 mx-1 text-xl font-weight-bold text-uppercase">Data Perusahaan</h6>
                </div>

                <div class="form-group">
                    <label for="namaToko">Nama Toko</label>
                    <input type="text" class="form-control border p-2" id="namaToko" name="nama_toko" placeholder="Masukkan nama toko" required>
                </div>

                <div class="form-group mt-2">
                    <label for="contactPerson">Contact Person</label>
                    <div class="d-flex flex-row">
                        <div class="">
                            <span class="input-group-text bg-gradient-info px-2 text-white" style="border-radius: 7px; border-bottom-right-radius:0; border-top-right-radius: 0;">+62</span>
                        </div>
                        <input type="text" class="form-control border p-2" style="border-radius: 7px; border-bottom-left-radius:0; border-top-left-radius: 0;" id="contactPerson" name="contact_person" placeholder="Masukkan Nomor Contact Person" required>
                    </div>
                </div>

                <div class="form-group mt-2">
                    <label for="alamatToko">Alamat Toko</label>
                    <textarea class="form-control border p-2" id="alamatToko" rows="3" name="alamat_toko" placeholder="Masukkan alamat toko"></textarea>
                </div>

                <div class="form-group mt-2">
                    <label for="kota">Kota Toko</label>
                    <input type="text" class="form-control border p-2" id="kota" name="kota_toko" placeholder="Kota">
                </div>

                <div class="form-group mt-4 mb-n2">
                    <h6 class="mb-0 mx-1 text-sm font-weight-bold text-uppercase">Sosial Media Perusahaan</h6>
                    <label for="kota">* Opsional</label>
                </div>

                <div class="form-group mt-2">
                    <label for="website">Website</label>
                    <input type="text" class="form-control border p-2" id="website" name="website" placeholder="Website">
                </div>

                <div class="form-row d-flex flex-row align-items-center justify-content-between mt-2">
                    <div class="form-group mt-2 w-50">
                        <label for="instagram">Instagram</label>
                        <input type="text" class="form-control border p-2" id="instagram" name="instagram" placeholder="Instagram">
                    </div>
                    <div class="form-group mt-2 w-50 ms-4">
                        <label for="facebook">Facebook</label>
                        <input type="text" class="form-control border p-2" id="facebook" name="facebook" placeholder="Facebook">
                    </div>
                </div>

                <div class="form-row d-flex flex-row align-items-center justify-content-between mt-2">
                    <div class="form-group mt-2 w-50">
                        <label for="shopee">Shopee</label>
                        <input type="text" class="form-control border p-2" id="shopee" name="shopee" placeholder="Shopee">
                    </div>
                    <div class="form-group mt-2 w-50 ms-4">
                        <label for="tokopedia">Tokopedia</label>
                        <input type="text" class="form-control border p-2" id="tokopedia" name="tokopedia" placeholder="Tokopedia">
                    </div>
                </div>

                <div class="form-row d-flex flex-row align-items-center justify-content-between mt-2">
                    <div class="form-group mt-2 w-50">
                        <label for="lazada">Lazada</label>
                        <input type="text" class="form-control border p-2" id="lazada" name="lazada" placeholder="Lazada">
                    </div>
                    <div class="form-group mt-2 w-50 ms-4">
                        <label for="other">Other</label>
                        <input type="text" class="form-control border p-2" id="other" name="other" placeholder="Other">
                    </div>
                </div>

                <button type="submit" class="btn bg-gradient-success mt-4">Simpan</button>
            </form>

        </div>
    </div>
</div>