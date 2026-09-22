<div class="container">
    <div class="card p-4 col-12 col-md-11">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-n3 lead font-weight-bold text-uppercase">Edit Data Customer Baru</h6>
                </div>
            </div>
        </div>
        <div class="card-body pb-0 p-0 mt-3">
            <?php
            $sqlCustomer = "SELECT * FROM cust WHERE id_cust = $id_customer";
            $resultCustomer = mysqli_query($conn, $sqlCustomer);
            while ($rowCust = mysqli_fetch_assoc($resultCustomer)) {
            ?>
                <form method="POST">
                    <div class="form-group mt-3">
                        <h6 class="mb-0 mx-1 text-xl font-weight-bold text-uppercase">Data Personal</h6>
                    </div>
                    <div class="form-group">
                        <label for="nama">Nama Pelanggan</label>
                        <input type="text" class="form-control border p-2" id="nama" name="nama_pelanggan" placeholder="Masukkan nama pelanggan" value="<?php echo $rowCust['nama']; ?>" required>
                    </div>
                    <div class="form-row d-flex flex-row align-items-center justify-content-between mt-2">
                        <div class="form-group mt-2 w-50">
                            <label for="noWhatsApp">No WhatsApp</label>
                            <div class="d-flex flex-row">
                                <div class="">
                                    <span class="input-group-text bg-gradient-info px-2 text-white" style="border-radius: 7px; border-bottom-right-radius:0; border-top-right-radius: 0;">+62</span>
                                </div>
                                <input type="text" class="form-control border p-2" style="border-radius: 7px; border-bottom-left-radius:0; border-top-left-radius: 0;" id="noWhatsApp" name="no_whatsapp" placeholder="Masukkan No WhatsApp" value="<?php echo $rowCust['no_wa']; ?>" required>
                            </div>
                        </div>
                        <div class="form-group mt-2 w-50 ms-4">
                            <label for="noTelp">No Telepon</label>
                            <div class="d-flex flex-row">
                                <div class="">
                                    <span class="input-group-text bg-gradient-info px-2 text-white" style="border-radius: 7px; border-bottom-right-radius:0; border-top-right-radius: 0;">+62</span>
                                </div>
                                <input type="text" class="form-control border p-2" style="border-radius: 7px; border-bottom-left-radius:0; border-top-left-radius: 0;" id="noTelp" name="no_telepon" placeholder="Masukkan No Telepon" value="<?php echo $rowCust['no_tlp']; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mt-2">
                        <label for="email">Email</label>
                        <input type="email" class="form-control border p-2" id="email" name="email_pelanggan" placeholder="Email" value="<?php echo $rowCust['email']; ?>">
                    </div>
                    <div class="form-group mt-2">
                        <label for="alamat">Alamat</label>
                        <textarea class="form-control border p-2" id="alamat" rows="3" name="alamat_pelanggan" placeholder="Masukkan alamat"><?php echo $rowCust['alamat']; ?></textarea>
                    </div>
                    <div class="form-group mt-2">
                        <label for="kota">Kota</label>
                        <input type="text" class="form-control border p-2" id="kota" name="kota_pelanggan" placeholder="Kota" value="<?php echo $rowCust['kota']; ?>">
                    </div>
                    <div class="form-group mt-2">
                        <label for="kategori">Kategori</label>
                        <select class="form-control border p-2" id="kategori" name="kategori_pelanggan">
                            <option value="dealer" <?php if ($rowCust['kategori'] == 'dealer') echo 'selected'; ?>>Dealer</option>
                            <option value="installer" <?php if ($rowCust['kategori'] == 'installer') echo 'selected'; ?>>Installer</option>
                        </select>
                    </div>
                    <div class="form-group mt-5">
                        <h6 class="mb-0 mx-1 text-xl font-weight-bold text-uppercase">Data Perusahaan</h6>
                    </div>
                    <div class="form-group">
                        <label for="namaToko">Nama Toko</label>
                        <input type="text" class="form-control border p-2" id="namaToko" name="nama_toko" placeholder="Masukkan nama toko" value="<?php echo $rowCust['nama_toko']; ?>" required>
                    </div>
                    <div class="form-group mt-2">
                        <label for="contactPerson">Contact Person</label>
                        <div class="d-flex flex-row">
                            <div class="">
                                <span class="input-group-text bg-gradient-info px-2 text-white" style="border-radius: 7px; border-bottom-right-radius:0; border-top-right-radius: 0;">+62</span>
                            </div>
                            <input type="text" class="form-control border p-2" style="border-radius: 7px; border-bottom-left-radius:0; border-top-left-radius: 0;" id="contactPerson" name="contact_person" placeholder="Masukkan Nomor Contact Person" value="<?php echo $rowCust['contact_person']; ?>" required>
                        </div>
                    </div>
                    <div class="form-group mt-2">
                        <label for="alamatToko">Alamat Toko</label>
                        <textarea class="form-control border p-2" id="alamatToko" rows="3" name="alamat_toko" placeholder="Masukkan alamat toko"><?php echo $rowCust['alamat_toko']; ?></textarea>
                    </div>
                    <div class="form-group mt-2">
                        <label for="kota">Kota Toko</label>
                        <input type="text" class="form-control border p-2" id="kota" name="kota_toko" placeholder="Kota" value="<?php echo $rowCust['kota_toko']; ?>">
                    </div>
                    <div class="form-group mt-4 mb-n2">
                        <h6 class="mb-0 mx-1 text-sm font-weight-bold text-uppercase">Sosial Media Perusahaan</h6>
                        <label for="kota">* Opsional</label>
                    </div>
                    <div class="form-group mt-2">
                        <label for="website">Website</label>
                        <input type="text" class="form-control border p-2" id="website" name="website" placeholder="Website" value="<?php echo $rowCust['website']; ?>">
                    </div>
                    <div class="form-row d-flex flex-row align-items-center justify-content-between mt-2">
                        <div class="form-group mt-2 w-50">
                            <label for="instagram">Instagram</label>
                            <input type="text" class="form-control border p-2" id="instagram" name="instagram" placeholder="Instagram" value="<?php echo $rowCust['ig']; ?>">
                        </div>
                        <div class="form-group mt-2 w-50 ms-4">
                            <label for="facebook">Facebook</label>
                            <input type="text" class="form-control border p-2" id="facebook" name="facebook" placeholder="Facebook" value="<?php echo $rowCust['fb']; ?>">
                        </div>
                    </div>
                    <div class="form-row d-flex flex-row align-items-center justify-content-between mt-2">
                        <div class="form-group mt-2 w-50">
                            <label for="shopee">Shopee</label>
                            <input type="text" class="form-control border p-2" id="shopee" name="shopee" placeholder="Shopee" value="<?php echo $rowCust['shopee']; ?>">
                        </div>
                        <div class="form-group mt-2 w-50 ms-4">
                            <label for="tokopedia">Tokopedia</label>
                            <input type="text" class="form-control border p-2" id="tokopedia" name="tokopedia" placeholder="Tokopedia" value="<?php echo $rowCust['tokped']; ?>">
                        </div>
                    </div>
                    <div class="form-row d-flex flex-row align-items-center justify-content-between mt-2">
                        <div class="form-group mt-2 w-50">
                            <label for="lazada">Lazada</label>
                            <input type="text" class="form-control border p-2" id="lazada" name="lazada" placeholder="Lazada" value="<?php echo $rowCust['lazada']; ?>">
                        </div>
                        <div class="form-group mt-2 w-50 ms-4">
                            <label for="other">Other</label>
                            <input type="text" class="form-control border p-2" id="other" name="other" placeholder="Other" value="<?php echo $rowCust['other']; ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn bg-gradient-success mt-4">Simpan</button>
                </form>
            <?php
            }
            ?>
        </div>
    </div>
</div>