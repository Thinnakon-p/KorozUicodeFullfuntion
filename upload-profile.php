     <?php
     if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profileImage'])) {
         $uploadDir = 'uploads/'; // โฟลเดอร์เก็บรูป (สร้างโฟลเดอร์นี้ก่อน)
         $fileName = uniqid() . '.' . pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);
         $uploadPath = $uploadDir . $fileName;

         if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadPath)) {
             // บันทึกลง database ถ้าต้องการ (เช่น UPDATE users SET profile_url = '$uploadPath' WHERE id = $user_id)
             echo json_encode(['success' => true, 'url' => $uploadPath]);
         } else {
             echo json_encode(['success' => false, 'message' => 'อัพโหลดล้มเหลว']);
         }
     }
     ?>
     