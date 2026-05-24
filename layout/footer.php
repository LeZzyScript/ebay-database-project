<?php
// layout/footer.php
// Shared footer — include at the BOTTOM of every page:
//
//   include("layout/footer.php");    // from root
//   include("../layout/footer.php"); // from subfolder
?>

<style>
    .flink {
        font-size: 13px;
        color: var(--text);
        cursor: default;
        user-select: none;
    }
    .footer-col ul li .flink:hover { text-decoration: none; }
    .footer-bottom-dead {
        color: var(--muted);
        font-size: 12px;
        cursor: default;
        user-select: none;
    }
</style>

<!-- ═══════════════════════════════════════
     FOOTER
═══════════════════════════════════════ -->
<footer>
    <div class="footer-grid">

        <!-- BUY -->
        <div class="footer-col">
            <h4>Buy</h4>
            <ul>
                <li><a href="../auth/register.php">Registration</a></li>
                <li><span class="flink">Bidding &amp; buying help</span></li>
                <li><span class="flink">Daily Deals</span></li>
                <li><span class="flink">Stores</span></li>
                <li><span class="flink">Saved searches</span></li>
                <li><span class="flink">Purchase history</span></li>
                <li><span class="flink">Buyer Protection</span></li>
                <li><span class="flink">eBay for Charity</span></li>
                <li><span class="flink">Creator Collections</span></li>
                <li><span class="flink">Seasonal Sales and events</span></li>
                <li><span class="flink">eBay Gift Cards</span></li>
            </ul>
        </div>

        <!-- SELL -->
        <div class="footer-col">
            <h4>Sell</h4>
            <ul>
                <li><span class="flink">Start selling</span></li>
                <li><span class="flink">How to sell</span></li>
                <li><span class="flink">Seller Hub</span></li>
                <li><span class="flink">Seller Center</span></li>
                <li><span class="flink">Seller Information Center</span></li>
                <li><span class="flink">Business sellers</span></li>
                <li><span class="flink">Seller News &amp; Updates</span></li>
                <li><span class="flink">Seller Protection</span></li>
                <li><span class="flink">Affiliates</span></li>
            </ul>
            <p class="footer-sub">Tools &amp; apps</p>
            <ul>
                <li><span class="flink">Developers</span></li>
                <li><span class="flink">Security center</span></li>
                <li><span class="flink">Site map</span></li>
                <li><span class="flink">eBay Stores</span></li>
            </ul>
        </div>

        <!-- COMPANIES + SOCIAL -->
        <div class="footer-col">
            <h4>eBay companies</h4>
            <ul>
                <li><a href="https://www.tcgplayer.com" target="_blank" rel="noopener">TCGplayer</a></li>
                <li><span class="flink">StubHub</span></li>
                <li><span class="flink">eBay Classifieds Group</span></li>
                <li><span class="flink">eBay Motors</span></li>
                <li><span class="flink">Magento</span></li>
            </ul>
            <p class="footer-sub">Stay connected</p>
            <div class="social-row">
                <a href="https://facebook.com/ebay" target="_blank" rel="noopener">
                    <span class="social-ico">f</span>Facebook
                </a>
            </div>
            <div class="social-row">
                <a href="https://twitter.com/ebay" target="_blank" rel="noopener">
                    <span class="social-ico" style="font-size:9px;">&#120143;</span>X (Twitter)
                </a>
            </div>
            <div class="social-row" style="margin-top:8px;">
                <span class="flink" style="display:flex;align-items:center;gap:8px;">
                    <span class="social-ico" style="font-size:10px;">in</span>LinkedIn
                </span>
            </div>
            <div class="social-row" style="margin-top:8px;">
                <span class="flink" style="display:flex;align-items:center;gap:8px;">
                    <span class="social-ico" style="font-size:10px;">▶</span>YouTube
                </span>
            </div>
        </div>

        <!-- ABOUT -->
        <div class="footer-col">
            <h4>About eBay</h4>
            <ul>
                <li><span class="flink">Company info</span></li>
                <li><span class="flink">News</span></li>
                <li><span class="flink">Investors</span></li>
                <li><span class="flink">Careers</span></li>
                <li><span class="flink">Diversity &amp; Inclusion</span></li>
                <li><span class="flink">Global Impact</span></li>
                <li><span class="flink">Government relations</span></li>
                <li><span class="flink">Advertise with us</span></li>
                <li><span class="flink">Policies</span></li>
                <li><span class="flink">Verified Rights Owner (VeRO) Program</span></li>
                <li><span class="flink">eCI Licenses</span></li>
                <li><span class="flink">Product Safety Tips</span></li>
                <li><span class="flink">Press Room</span></li>
                <li><span class="flink">Sustainability</span></li>
            </ul>
        </div>

        <!-- HELP + SITES -->
        <div class="footer-col">
            <h4>Help &amp; Contact</h4>
            <ul>
                <li><span class="flink">Seller Center</span></li>
                <li><span class="flink">Contact Us</span></li>
                <li><span class="flink">eBay Returns</span></li>
                <li><span class="flink">eBay Money Back Guarantee</span></li>
                <li><span class="flink">Resolution Center</span></li>
                <li><span class="flink">Live Chat Support</span></li>
                <li><span class="flink">Returns Center</span></li>
                <li><span class="flink">Buyer Protection</span></li>
            </ul>
            <p class="footer-sub">Community</p>
            <ul>
                <li><span class="flink">Announcements</span></li>
                <li><span class="flink">eBay Community</span></li>
                <li><span class="flink">eBay for Business Podcast</span></li>
                <li><span class="flink">Discussion Boards</span></li>
                <li><span class="flink">Groups</span></li>
            </ul>
            <p class="footer-sub">eBay Sites</p>
            <div class="loc-pill">
                &#127477;&#127469;&nbsp;Philippines
                <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        </div>

    </div><!-- /.footer-grid -->

    <!-- Copyright bar -->
    <div class="footer-bottom">
        <p>
            Copyright &copy; 1995&ndash;<?= date('Y') ?> eBay Inc. All Rights Reserved.
            <span class="footer-bottom-dead">Accessibility</span>,
            <span class="footer-bottom-dead">User Agreement</span>,
            <span class="footer-bottom-dead">Privacy</span>,
            <span class="footer-bottom-dead">Consumer Health Data</span>,
            <span class="footer-bottom-dead">Payments Terms of Use</span>,
            <span class="footer-bottom-dead">Cookies</span>,
            <span class="footer-bottom-dead">CA Privacy Notice</span>,
            <span class="footer-bottom-dead">Your Privacy Choices</span> and
            <span class="footer-bottom-dead">AdChoice</span>
        </p>
    </div>

</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</body>
</html>
