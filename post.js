firebase.database().ref('posts/').push(post);

// ดึงข้อมูลแสดง
firebase.database().ref('posts/').on('value', snapshot => {
  const posts = snapshot.val();
  displayPosts(posts);
});